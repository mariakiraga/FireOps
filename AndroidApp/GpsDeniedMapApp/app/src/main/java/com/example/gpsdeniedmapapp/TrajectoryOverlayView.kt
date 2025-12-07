package com.example.gpsdeniedmapapp

import android.content.Context
import android.graphics.Canvas
import android.graphics.Matrix
import android.graphics.Paint
import android.graphics.Path
import android.graphics.PointF
import android.util.AttributeSet
import android.view.View

class TrajectoryOverlayView @JvmOverloads constructor(
    context: Context,
    attrs: AttributeSet? = null,
    defStyleAttr: Int = 0
) : View(context, attrs, defStyleAttr) {

    // Punkty ścieżki w układzie bitmapy mapy (px)
    private var pathBitmapPoints: List<PointF> = emptyList()

    // Matrix używany do rysowania mapy (skalowanie + translacja + rotacja)
    private var mapMatrix: Matrix? = null


    private val pathPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        style = Paint.Style.STROKE
        strokeWidth = 6f
        color = 0xFFFFFF00.toInt() // żółty
    }

    private val pointPaint = Paint(Paint.ANTI_ALIAS_FLAG).apply {
        style = Paint.Style.FILL
        color = 0xFFFFFF00.toInt() // żółty, wypełnione kółko
    }
    fun setTrajectoryBitmapPoints(points: List<PointF>) {
        pathBitmapPoints = points
        invalidate()
    }

    fun updateMapMatrix(matrix: Matrix) {
        // robimy kopię, żeby z zewnątrz nikt nie nadpisał referencji
        mapMatrix = Matrix(matrix)
        invalidate()
    }

    override fun onDraw(canvas: Canvas) {
        super.onDraw(canvas)

        val matrix = mapMatrix ?: return
        if (pathBitmapPoints.isEmpty()) return

        val pts = FloatArray(pathBitmapPoints.size * 2)
        for ((i, p) in pathBitmapPoints.withIndex()) {
            pts[2 * i] = p.x
            pts[2 * i + 1] = p.y
        }

        matrix.mapPoints(pts)

        if (pathBitmapPoints.size == 1) {
            // tylko punkt → wypełnione kółko
            val cx = pts[0]
            val cy = pts[1]
            canvas.drawCircle(cx, cy, 10f, pointPaint)
        } else {
            // linia ścieżki
            val path = Path()
            path.moveTo(pts[0], pts[1])
            for (i in 1 until pathBitmapPoints.size) {
                val x = pts[2 * i]
                val y = pts[2 * i + 1]
                path.lineTo(x, y)
            }
            canvas.drawPath(path, pathPaint)

            // punkt końcowy ścieżki – wypełnione kółko
            val lastX = pts[pts.size - 2]
            val lastY = pts[pts.size - 1]
            canvas.drawCircle(lastX, lastY, 10f, pointPaint)
        }
    }

}
