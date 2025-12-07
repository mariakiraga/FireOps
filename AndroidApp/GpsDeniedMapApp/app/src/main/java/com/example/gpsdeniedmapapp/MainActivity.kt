package com.example.gpsdeniedmapapp

import android.content.Context
import android.graphics.Matrix
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.os.Bundle
import android.util.Log
import android.widget.Button
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.TextView
import androidx.activity.ComponentActivity
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import kotlin.math.roundToInt

data class ImuFrame(
    val tNs: Long,
    val acc: FloatArray,
    val gyro: FloatArray,
    val rv: FloatArray
)

class MainActivity : ComponentActivity(), SensorEventListener {

    private lateinit var imageMap: ImageView
    private lateinit var textMetadata: TextView
    private lateinit var textHeading: TextView
    private lateinit var compassRose: ImageView
    private lateinit var buttonStart: Button
    private lateinit var metadataHeader: LinearLayout
    private lateinit var metadataChevron: ImageView
    private lateinit var textMetadataTitle: TextView
    private lateinit var trajectoryOverlay: TrajectoryOverlayView

    private var isMetadataExpanded: Boolean = false

    // --- METADANE MAPY ---
    private val mapMetadata = MapMetadata(
        pixelsPerMeter = 7.7633587f,      // px / m
        topLeftLat = 53.17163889,
        topLeftLon = 18.04502778,
        northRotationDeg = 0f
        // prawy dolny: 53.169278,18.0486033
    )

    // --- CZUJNIKI / KOMPAS + IMU ---
    private lateinit var sensorManager: SensorManager
    private var rotationVectorSensor: Sensor? = null
    private var gameRotationVectorSensor: Sensor? = null
    private var accelSensor: Sensor? = null
    private var gyroSensor: Sensor? = null
    private var gravitySensor: Sensor? = null
    private var linearAccelSensor: Sensor? = null
    private var magnetometerSensor: Sensor? = null
    private var stepCounterSensor: Sensor? = null

    private var initialStepCount: Float? = null
    private val rotationMatrix = FloatArray(9)
    private val orientationAngles = FloatArray(3)

    private var currentHeadingDeg: Float = 0f
    private var filteredHeadingDeg: Float = 0f
    private val headingFilterAlpha = 0.15f

    // --- IMU frames dla nowego formatu ---
    private val currentImuFrames = mutableListOf<ImuFrame>()
    private val imuBatchSize = 200

    // ostatnie znane wartości z GYRO i GAME_RV
    private var lastGyroValues: FloatArray? = null
    private var lastGameRvValues: FloatArray? = null

    // --- Częstotliwość ACCE (jak wcześniej) ---
    private var accSamplesInWindow: Int = 0
    private var accWindowStartNs: Long = 0L
    private var accFreqHz: Double = 0.0

    // --- Status wysyłki ---
    private var lastSendStatus: String = "N/A"
    private var lastSendTime: Long = 0L

    // --- Mapa / trajektoria ---
    private var mapBitmapWidth: Int = 0
    private var mapBitmapHeight: Int = 0
    private val trajectoryMeters = mutableListOf<Pair<Float, Float>>()

    // "start" = bieżąca lokalizacja względem początku sesji
    private var currentStartX: Float = 0f
    private var currentStartY: Float = 0f

    // --- HTTP ---
    private val imuEndpointUrl = "https://19a4c82bfcd8.ngrok-free.app/predict_position/FF-100" //"https://portal.forpet.biz/hacknation/imu_endpoint.php"
    private val httpClient = OkHttpClient()

    // --- Biometria (placeholder, na razie nie wysyłamy w JSON) ---
    @Volatile
    private var latestHeartRateBpm: Float? = 75f

    @Volatile
    private var latestSkinTempC: Float? = 33.5f

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        // UI
        imageMap = findViewById(R.id.image_map)
        metadataHeader = findViewById(R.id.metadata_header)
        metadataChevron = findViewById(R.id.image_metadata_chevron)
        textMetadataTitle = findViewById(R.id.text_metadata_title)
        textMetadata = findViewById(R.id.text_metadata)
        textHeading = findViewById(R.id.text_heading)
        compassRose = findViewById(R.id.image_compass_rose)
        buttonStart = findViewById(R.id.button_start)
        trajectoryOverlay = findViewById(R.id.trajectory_overlay)

        metadataHeader.setOnClickListener {
            isMetadataExpanded = !isMetadataExpanded
            if (isMetadataExpanded) {
                textMetadata.visibility = android.view.View.VISIBLE
                metadataChevron.rotation = 180f
            } else {
                textMetadata.visibility = android.view.View.GONE
                metadataChevron.rotation = 0f
            }
        }

        imageMap.scaleType = ImageView.ScaleType.MATRIX
        imageMap.imageMatrix = Matrix()

        // Sensory
        sensorManager = getSystemService(Context.SENSOR_SERVICE) as SensorManager
        rotationVectorSensor = sensorManager.getDefaultSensor(Sensor.TYPE_ROTATION_VECTOR)
        gameRotationVectorSensor = sensorManager.getDefaultSensor(Sensor.TYPE_GAME_ROTATION_VECTOR)
        accelSensor = sensorManager.getDefaultSensor(Sensor.TYPE_ACCELEROMETER)
        gyroSensor = sensorManager.getDefaultSensor(Sensor.TYPE_GYROSCOPE)
        gravitySensor = sensorManager.getDefaultSensor(Sensor.TYPE_GRAVITY)
        linearAccelSensor = sensorManager.getDefaultSensor(Sensor.TYPE_LINEAR_ACCELERATION)
        magnetometerSensor = sensorManager.getDefaultSensor(Sensor.TYPE_MAGNETIC_FIELD)
        stepCounterSensor = sensorManager.getDefaultSensor(Sensor.TYPE_STEP_COUNTER)

        // START/STOP
        buttonStart.setOnClickListener {
            toggleRecording()
        }

        // początkowy tekst
        updateMetadataText()
    }

    // --- METADATA PANEL ---

    private fun updateMetadataText() {
        val sb = StringBuilder()

        // 1. Map info
        sb.appendLine("MAP:")
        sb.appendLine("  px/m: ${mapMetadata.pixelsPerMeter}")
        sb.appendLine("  top-left: ${mapMetadata.topLeftLat}, ${mapMetadata.topLeftLon}")
        sb.appendLine("  north rot: ${mapMetadata.northRotationDeg}°")
        sb.appendLine()

        // 2. Recording
        sb.appendLine("RECORDING:")
        sb.appendLine("  active: ${if (isRecording) "YES" else "NO"}")
        sb.appendLine()

        // 3. Sensors availability
        sb.appendLine("SENSORS:")
        sb.appendLine("  ACCE: ${if (accelSensor != null) "OK" else "MISSING"}")
        sb.appendLine("  GYRO: ${if (gyroSensor != null) "OK" else "MISSING"}")
        sb.appendLine("  GRAVITY: ${if (gravitySensor != null) "OK" else "MISSING"}")
        sb.appendLine("  LINACCE: ${if (linearAccelSensor != null) "OK" else "MISSING"}")
        sb.appendLine("  MAGNET: ${if (magnetometerSensor != null) "OK" else "MISSING"}")
        sb.appendLine("  STEP: ${if (stepCounterSensor != null) "OK" else "MISSING"}")
        sb.appendLine("  ROT_VEC: ${if (rotationVectorSensor != null) "OK" else "MISSING"}")
        sb.appendLine("  GAME_RV: ${if (gameRotationVectorSensor != null) "OK" else "MISSING"}")
        sb.appendLine()

        // 4. Frequencja (ACCE)
        sb.appendLine("FREQUENCY:")
        val freqStr = if (accFreqHz > 0.0) "${accFreqHz.toInt()} Hz" else "-"
        sb.appendLine("  ACCE: $freqStr")
        sb.appendLine()

        // 5. Endpoint status
        sb.appendLine("ENDPOINT:")
        sb.appendLine("  last: $lastSendStatus")
        if (lastSendTime != 0L) {
            sb.appendLine("  at: $lastSendTime")
        }
        sb.appendLine()

        // 6. BIO (na razie tylko do podglądu, nie w JSON)
        sb.appendLine("BIO:")
        val hrStr = latestHeartRateBpm?.let { "${it.toInt()} bpm" } ?: "-"
        val skinStr = latestSkinTempC?.let { String.format("%.1f °C", it) } ?: "-"
        sb.appendLine("  HR: $hrStr")
        sb.appendLine("  Skin temp: $skinStr")

        // 7. START (bieżąca pozycja odniesienia)
        sb.appendLine()
        sb.appendLine("START POS:")
        sb.appendLine("  x: $currentStartX")
        sb.appendLine("  y: $currentStartY")

        runOnUiThread {
            textMetadata.text = sb.toString()
        }
    }

    // --- Lifecycle ---

    override fun onResume() {
        super.onResume()
        rotationVectorSensor?.also {
            sensorManager.registerListener(this, it, SensorManager.SENSOR_DELAY_FASTEST)
        }
        gameRotationVectorSensor?.also {
            sensorManager.registerListener(this, it, SensorManager.SENSOR_DELAY_FASTEST)
        }
        accelSensor?.also {
            sensorManager.registerListener(this, it, SensorManager.SENSOR_DELAY_FASTEST)
        }
        gyroSensor?.also {
            sensorManager.registerListener(this, it, SensorManager.SENSOR_DELAY_FASTEST)
        }
        gravitySensor?.also {
            sensorManager.registerListener(this, it, SensorManager.SENSOR_DELAY_FASTEST)
        }
        linearAccelSensor?.also {
            sensorManager.registerListener(this, it, SensorManager.SENSOR_DELAY_FASTEST)
        }
        magnetometerSensor?.also {
            sensorManager.registerListener(this, it, SensorManager.SENSOR_DELAY_FASTEST)
        }
        stepCounterSensor?.also {
            sensorManager.registerListener(this, it, SensorManager.SENSOR_DELAY_FASTEST)
        }
    }

    override fun onPause() {
        super.onPause()
        sensorManager.unregisterListener(this)
    }

    // --- START / STOP nagrywania IMU ---

    private var isRecording: Boolean = false

    private fun toggleRecording() {
        isRecording = !isRecording
        if (isRecording) {
            // nowa sesja
            currentImuFrames.clear()
            lastGyroValues = null
            lastGameRvValues = null
            initialStepCount = null

            // reset trajektorii / pozycji odniesienia
            currentStartX = 0f
            currentStartY = 0f
            trajectoryMeters.clear()
            trajectoryOverlay.setTrajectoryBitmapPoints(emptyList())

            buttonStart.text = "STOP"
        } else {
            // jeśli coś jeszcze zostało w buforze – wyślij ostatni niepełny batch
            if (currentImuFrames.isNotEmpty()) {
                val framesToSend = currentImuFrames.toList()
                currentImuFrames.clear()
                sendImuBatch(framesToSend)
            }
            buttonStart.text = "START"
        }
        updateMetadataText()
    }

    // --- SensorEventListener ---

    override fun onSensorChanged(event: SensorEvent?) {
        if (event == null) return
        val nowMillis = System.currentTimeMillis()

        when (event.sensor.type) {
            Sensor.TYPE_ROTATION_VECTOR -> {
                // dalej używamy go tylko do kompasu / mapy
                handleRotationVector(event)
            }

            Sensor.TYPE_GAME_ROTATION_VECTOR -> {
                // przechowujemy ostatnią wartość GAME_RV (z w)
                lastGameRvValues = event.values.clone()
            }

            Sensor.TYPE_ACCELEROMETER -> {
                if (isRecording) {
                    handleAccelerometerForImu(event)
                }
                updateAccFrequency(event.timestamp)
            }

            Sensor.TYPE_GYROSCOPE -> {
                if (isRecording) {
                    lastGyroValues = event.values.clone()
                }
            }

            Sensor.TYPE_GRAVITY -> {
                // na razie nie używamy w JSON
            }

            Sensor.TYPE_LINEAR_ACCELERATION -> {
                // na razie nie używamy w JSON
            }

            Sensor.TYPE_MAGNETIC_FIELD -> {
                // na razie nie używamy w JSON
            }

            Sensor.TYPE_STEP_COUNTER -> {
                // jeśli chcesz używać kroków, można je nadal aktualizować lokalnie
                if (isRecording) {
                    val rawSteps = event.values[0]
                    if (initialStepCount == null) {
                        initialStepCount = rawSteps
                    }
                    val stepsSinceStart = rawSteps - (initialStepCount ?: rawSteps)
                    // np. tylko do podglądu w METADATA w przyszłości
                }
            }
        }
    }

    override fun onAccuracyChanged(sensor: Sensor?, accuracy: Int) {
        // nic
    }

    // --- Budowanie IMU frame z ACCE + ostatnie GYRO + GAME_RV ---

    private fun handleAccelerometerForImu(event: SensorEvent) {
        val accValues = event.values.clone()
        val gyroValues = (lastGyroValues ?: floatArrayOf(0f, 0f, 0f)).clone()

        val rvRaw = lastGameRvValues ?: floatArrayOf(0f, 0f, 0f, 1f)
        val rvValues = when {
            rvRaw.size >= 4 -> rvRaw.clone()
            rvRaw.size == 3 -> floatArrayOf(rvRaw[0], rvRaw[1], rvRaw[2], 1f)
            else -> floatArrayOf(0f, 0f, 0f, 1f)
        }

        val frame = ImuFrame(
            tNs = event.timestamp, // timestamp w nanosekundach
            acc = accValues,
            gyro = gyroValues,
            rv = rvValues
        )

        currentImuFrames.add(frame)

        // gdy mamy 200 rekordów, wysyłamy batch
        if (currentImuFrames.size >= imuBatchSize) {
            val framesToSend = currentImuFrames.toList()
            currentImuFrames.clear()
            sendImuBatch(framesToSend)
        }
    }

    // --- Kompas / heading ---

    private fun handleRotationVector(event: SensorEvent) {
        SensorManager.getRotationMatrixFromVector(rotationMatrix, event.values)
        SensorManager.getOrientation(rotationMatrix, orientationAngles)

        var azimuthDeg = Math.toDegrees(orientationAngles[0].toDouble()).toFloat()
        if (azimuthDeg < 0) azimuthDeg += 360f

        currentHeadingDeg = azimuthDeg

        filteredHeadingDeg = if (filteredHeadingDeg == 0f) {
            currentHeadingDeg
        } else {
            val diff = angleDiffDegrees(filteredHeadingDeg, currentHeadingDeg)
            filteredHeadingDeg + headingFilterAlpha * diff
        }

        updateHeadingText(filteredHeadingDeg)
        updateMapRotation(filteredHeadingDeg)
        updateCompassRose(filteredHeadingDeg)
    }

    private fun angleDiffDegrees(from: Float, to: Float): Float {
        return (to - from + 540f) % 360f - 180f
    }

    private fun updateHeadingText(headingDeg: Float) {
        val rounded = headingDeg.roundToInt()
        val dir = when {
            rounded in 315..360 || rounded in 0..45 -> "N"
            rounded in 46..134 -> "E"
            rounded in 135..224 -> "S"
            else -> "W"
        }
        textHeading.text = "${rounded}° ($dir)"
    }

    private fun updateMapRotation(userHeadingDeg: Float) {
        val angle = mapMetadata.northRotationDeg - userHeadingDeg

        val matrix = Matrix()
        val drawable = imageMap.drawable ?: return

        val intrinsicWidth = drawable.intrinsicWidth.toFloat()
        val intrinsicHeight = drawable.intrinsicHeight.toFloat()

        val viewWidth = imageMap.width.toFloat()
        val viewHeight = imageMap.height.toFloat()
        if (viewWidth == 0f || viewHeight == 0f) return

        val scale = maxOf(viewWidth / intrinsicWidth, viewHeight / intrinsicHeight)
        val dx = (viewWidth - intrinsicWidth * scale) / 2f
        val dy = (viewHeight - intrinsicHeight * scale) / 2f

        matrix.postScale(scale, scale)
        matrix.postTranslate(dx, dy)

        val centerX = viewWidth / 2f
        val centerY = viewHeight / 2f
        matrix.postRotate(angle, centerX, centerY)

        imageMap.imageMatrix = matrix

        trajectoryOverlay.updateMapMatrix(matrix)

        if (mapBitmapWidth == 0 || mapBitmapHeight == 0) {
            val d = imageMap.drawable
            if (d != null) {
                mapBitmapWidth = d.intrinsicWidth
                mapBitmapHeight = d.intrinsicHeight
            }
        }
    }

    private fun updateCompassRose(userHeadingDeg: Float) {
        val angle = mapMetadata.northRotationDeg - userHeadingDeg
        compassRose.rotation = angle
    }

    // --- Trajektoria ---

    private fun setTrajectoryFromMeters(positions: List<Pair<Float, Float>>) {
        if (mapBitmapWidth == 0 || mapBitmapHeight == 0) {
            return
        }

        val pxPerMeter = mapMetadata.pixelsPerMeter
        val startX = mapBitmapWidth / 2f
        val startY = mapBitmapHeight / 2f

        val bitmapPoints = positions.map { (mx, my) ->
            val px = startX + mx * pxPerMeter
            val py = startY + my * pxPerMeter
            android.graphics.PointF(px, py)
        }

        runOnUiThread {
            trajectoryOverlay.setTrajectoryBitmapPoints(bitmapPoints)
            updateMetadataText()
        }
    }

    private fun handleTrajectoryResponse(jsonString: String) {
        try {
            val obj = JSONObject(jsonString)

            // --- NOWY FORMAT:
            // {
            //   "inference_time_s": ...,
            //   "new_position": [x, y]
            // }
            if (obj.has("new_position")) {
                val posArr = obj.getJSONArray("new_position")
                if (posArr.length() >= 2) {
                    val x = posArr.getDouble(0).toFloat()
                    val y = posArr.getDouble(1).toFloat()

                    // (opcjonalnie) log inference_time_s
                    if (obj.has("inference_time_s")) {
                        val infTime = obj.getDouble("inference_time_s")
                        Log.d("endpoint", "Inference time: $infTime s")
                    }

                    // aktualizujemy "start" na kolejne batch'e
                    currentStartX = x
                    currentStartY = y

                    // dokładamy punkt do trajektorii i rysujemy
                    trajectoryMeters.add(x to y)
                    setTrajectoryFromMeters(trajectoryMeters)
                }
                return
            }

            // --- Fallback: stare formaty (jeśli jeszcze czasem je dostaniesz) ---
            if (obj.has("positions")) {
                val arr = obj.getJSONArray("positions")
                val newPositions = mutableListOf<Pair<Float, Float>>()

                for (i in 0 until arr.length()) {
                    val pObj = arr.getJSONObject(i)
                    if (!pObj.has("pos")) continue
                    val posArr = pObj.getJSONArray("pos")
                    if (posArr.length() < 2) continue

                    val x = posArr.getDouble(0).toFloat()
                    val y = posArr.getDouble(1).toFloat()
                    newPositions.add(x to y)
                }

                if (newPositions.isNotEmpty()) {
                    trajectoryMeters.addAll(newPositions)
                    val last = trajectoryMeters.last()
                    currentStartX = last.first
                    currentStartY = last.second
                    setTrajectoryFromMeters(trajectoryMeters)
                }
                return
            }

            // drugi fallback: { "x": ..., "y": ... }
            if (obj.has("x") && obj.has("y")) {
                val x = obj.getDouble("x").toFloat()
                val y = obj.getDouble("y").toFloat()

                currentStartX = x
                currentStartY = y
                trajectoryMeters.add(x to y)
                setTrajectoryFromMeters(trajectoryMeters)
            }

        } catch (e: Exception) {
            Log.e("endpoint", "Trajectory parse error", e)
        }
    }


    // --- IMU JSON (nowy format) ---

    private fun buildImuJson(frames: List<ImuFrame>): String {
        val root = JSONObject()

        // "start": [x0, y0]
        val startArr = JSONArray()
        startArr.put(currentStartX.toDouble())
        startArr.put(currentStartY.toDouble())
        root.put("start", startArr)

        // "imu_data": [...]
        val imuArr = JSONArray()
        for (f in frames) {
            val obj = JSONObject()
            obj.put("t_ns", f.tNs)

            val accArr = JSONArray()
            accArr.put(f.acc[0].toDouble())
            accArr.put(f.acc[1].toDouble())
            accArr.put(f.acc[2].toDouble())
            obj.put("acc", accArr)

            val gyroArr = JSONArray()
            gyroArr.put(f.gyro[0].toDouble())
            gyroArr.put(f.gyro[1].toDouble())
            gyroArr.put(f.gyro[2].toDouble())
            obj.put("gyro", gyroArr)

            val rvArr = JSONArray()
            rvArr.put(f.rv[0].toDouble())
            rvArr.put(f.rv[1].toDouble())
            rvArr.put(f.rv[2].toDouble())
            rvArr.put(f.rv.getOrElse(3) { 1f }.toDouble())
            obj.put("rv", rvArr)

            imuArr.put(obj)
        }

        root.put("imu_data", imuArr)
        return root.toString()
    }

    private fun sendImuBatch(frames: List<ImuFrame>) {
        val jsonString = buildImuJson(frames)

        Thread {
            try {
                val mediaType = "application/json; charset=utf-8".toMediaType()
                val body = jsonString.toRequestBody(mediaType)
                val request = Request.Builder()
                    .url(imuEndpointUrl)
                    .addHeader("ngrok-skip-browser-warning", "true")
                    .post(body)
                    .build()

                val response = httpClient.newCall(request).execute()
                response.use { resp ->
                    lastSendStatus = "OK (${resp.code})"
                    lastSendTime = System.currentTimeMillis()

                    val bodyString = resp.body?.string()

                    Log.d("endpoint", "Request body:\n$jsonString")
                    Log.d("endpoint", "Response code: ${resp.code}")
                    Log.d("endpoint", "Response body:\n$bodyString")

                    if (resp.isSuccessful && bodyString != null) {
                        try {
                            handleTrajectoryResponse(bodyString)
                        } catch (e: Exception) {
                            Log.e("endpoint", "Trajectory parse error", e)
                        }
                    }
                }

                runOnUiThread {
                    updateMetadataText()
                }
            } catch (e: Exception) {
                e.printStackTrace()
                lastSendStatus = "ERROR: ${e.javaClass.simpleName}"
                lastSendTime = System.currentTimeMillis()
                runOnUiThread {
                    updateMetadataText()
                }
            }
        }.start()
    }

    // --- ACC frequency ---

    private fun updateAccFrequency(eventTimestampNs: Long) {
        if (accWindowStartNs == 0L) {
            accWindowStartNs = eventTimestampNs
            accSamplesInWindow = 0
        }

        accSamplesInWindow++

        val windowDurationNs = eventTimestampNs - accWindowStartNs
        if (windowDurationNs >= 500_000_000L) { // 0.5 s
            val windowSec = windowDurationNs / 1_000_000_000.0
            accFreqHz = accSamplesInWindow / windowSec

            accWindowStartNs = eventTimestampNs
            accSamplesInWindow = 0

            updateMetadataText()
        }
    }
}
