package com.example.gpsdeniedmapapp

data class MapMetadata(
    val pixelsPerMeter: Float,   // ile pikseli przypada na 1 metr
    val topLeftLat: Double,      // szerokość geograficzna lewego górnego rogu
    val topLeftLon: Double,      // długość geograficzna lewego górnego rogu
    val northRotationDeg: Float  // jak mapa jest obracana względem geograficznej północy (0° = góra mapy = północ)
)
