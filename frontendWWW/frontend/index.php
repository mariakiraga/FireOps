<?php
// index.php – panel monitoringu strażaków
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Monitoring strażaków – panel dowodzenia</title>
    <!-- Bootstrap CSS -->
    <link 
        rel="stylesheet" 
        href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css"
    >
    <!-- Leaflet CSS -->
    <link 
        rel="stylesheet" 
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >
	<link
		rel="stylesheet"
		href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css"
	/>
    <style>
        html, body {
            height: 100%;
        }
        body {
            background-color: #121212;
            color: #f5f5f5;
        }

        .navbar-straza {
            background-color: #000000;
            border: none;
            border-radius: 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.4);
        }
        .navbar-straza .navbar-brand,
        .navbar-straza .navbar-nav > li > a {
            color: #fff !important;
        }

        .status-chip {
            display: inline-block;
            padding: 4px 10px;
            margin-right: 5px;
            border-radius: 16px;
            background-color: rgba(255,255,255,0.1);
            font-size: 12px;
        }

        .main-container {
            padding-top: 60px;
            height: calc(100vh - 60px);
        }

        #map-container {
            background: #212121;
            height: 100%;
            padding: 10px;
        }

        #map {
            width: 100%;
            height: 100%;
            border-radius: 8px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.6);
        }

        #sidebar {
            background: #1e1e1e;
            height: 100%;
            padding: 10px;
            border-left: 1px solid #333;
            box-shadow: -2px 0 6px rgba(0,0,0,0.5);
        }

        .firefighter-card {
            background: #262626;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.4);
            cursor: pointer;
            transition: box-shadow 0.15s ease, transform 0.1s ease;
        }

        .firefighter-card:hover {
            box-shadow: 0 3px 8px rgba(0,0,0,0.6);
            transform: translateY(-1px);
        }

        .firefighter-name {
            font-weight: 600;
            font-size: 14px;
        }

        .firefighter-status {
            font-size: 12px;
            color: #ffcc80;
        }

        .sidebar-header {
            margin-bottom: 10px;
        }

        .sidebar-header h4 {
            margin-top: 0;
            margin-bottom: 5px;
        }

        .sidebar-header small {
            color: #bdbdbd;
        }

        #firefighters-list {
            max-height: calc(100vh - 60px - 60px);
            overflow-y: auto;
        }

        .status-label-ok {
            color: #a5d6a7;
        }
        .status-label-alert {
            color: #ff8a80;
        }

        .firefighter-icon-small {
            width: 32px;
            height: 32px;
        }
		
		.ff-icon {
			color: #ffb74d; /* pomarańczowy akcent dla ikon */
			margin-right: 4px;
		}

		.ff-icon-main {
			color: #ffcc80;
			margin-right: 6px;
			font-size: 14px;
		}

		.ff-icon-heart {
			color: #ef5350;
		}

		.ff-icon-thermo {
			color: #42a5f5;
		}

		.ff-icon-air {
			color: #29b6f6;
		}

		.ff-icon-battery {
			color: #81c784;
		}

		.ff-icon-alert {
			color: #ff7043;
		}

		.ff-value,
		.ff-value-strong {
			color: #f5f5f5;
		}

		.ff-value-strong {
			font-weight: 600;
		}

		.ff-id {
			color: #bdbdbd;
		}

		.compact-row {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 2px;
		}

		.ff-separator {
			color: #757575;
			margin: 0 4px;
		}
		#firefighters-filters {
			display: none; /* domyślnie schowany */
			margin-bottom: 10px;
			padding: 8px;
			background: #222;
			border-radius: 6px;
			border: 1px solid #333;
		}

		#firefighters-filters .form-group {
			margin-bottom: 6px;
		}

		#firefighters-filters label {
			font-size: 11px;
			color: #bdbdbd;
			margin-bottom: 2px;
		}

		#firefighters-filters .form-control {
			background-color: #121212;
			border: 1px solid #424242;
			color: #f5f5f5;
			height: 26px;
			padding: 2px 6px;
			font-size: 12px;
		}

		#firefighters-filters .form-control:focus {
			border-color: #ff9800;
			box-shadow: none;
		}

		#toggle-filters {
			background-color: #2c2c2c;
			border-color: #424242;
			color: #f5f5f5;
		}

		#toggle-filters:hover {
			background-color: #424242;
		}
		.ff-icon {
			color: #ffb74d;
			margin-right: 4px;
		}

		.ff-icon-main {
			color: #ffcc80;
			margin-right: 6px;
			font-size: 14px;
		}

		.ff-icon-heart {
			color: #ef5350;
		}

		.ff-icon-thermo {
			color: #42a5f5;
		}

		.ff-icon-air {
			color: #29b6f6;
		}

		.ff-icon-battery {
			color: #81c784;
		}

		.ff-icon-alert {
			color: #ff7043;
		}

		.ff-value,
		.ff-value-strong {
			color: #f5f5f5;
		}

		.ff-value-strong {
			font-weight: 600;
		}

		.ff-id {
			color: #bdbdbd;
		}

		.compact-row {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 2px;
		}

		.ff-separator {
			color: #757575;
			margin: 0 4px;
		}
		/* Przyciski w headerze panelu */
		#toggle-filters,
		#toggle-compact,
		#toggle-full {
			background-color: #2c2c2c;
			border-color: #424242;
			color: #f5f5f5;
		}

		#toggle-filters:hover,
		#toggle-compact:hover,
		#toggle-full:hover {
			background-color: #424242;
		}

		/* Widok kompaktowy kart */
		.firefighter-card.compact {
			padding: 6px 8px;
			margin-bottom: 6px;
		}

		.ff-row-top {
			display: flex;
			align-items: center;
			justify-content: space-between;
			margin-bottom: 2px;
		}

		.ff-row-main,
		.ff-row-alerts {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 4px;
			font-size: 11px;
		}

		.ff-status-chip {
			border-radius: 999px;
			padding: 2px 6px;
			font-size: 10px;
			text-transform: uppercase;
		}

		.ff-status-chip-ok {
			background-color: rgba(76,175,80,0.15);
			color: #a5d6a7;
		}

		.ff-status-chip-alert {
			background-color: rgba(244,67,54,0.15);
			color: #ef9a9a;
		}

		.ff-status-chip-offline {
			background-color: rgba(158,158,158,0.15);
			color: #e0e0e0;
		}

		.ff-name {
			font-weight: 600;
		}

		.ff-id {
			color: #9e9e9e;
			margin-left: 4px;
			font-size: 11px;
		}
.alert-card {
    background: #1e1e1e;
    border-radius: 6px;
    padding: 6px 8px;
    margin-bottom: 6px;
    border: 1px solid #333;
    cursor: pointer;
}

.alert-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.alert-label {
    padding: 2px 6px;
    border-radius: 999px;
    font-size: 11px;
    text-transform: uppercase;
}

.alert-chip-critical {
    background-color: rgba(244,67,54,0.2);
    color: #ef9a9a;
}

.alert-chip-warning {
    background-color: rgba(255,152,0,0.2);
    color: #ffcc80;
}

.alert-chip-info {
    background-color: rgba(33,150,243,0.2);
    color: #90caf9;
}

.alert-time {
    font-size: 11px;
    color: #bdbdbd;
}

.alert-card-body {
    font-size: 11px;
    color: #e0e0e0;
}

.alert-card-body i {
    margin-right: 4px;
}

.alert-desc {
    margin-top: 2px;
    font-style: italic;
    color: #bdbdbd;
}
		
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-inverse navbar-straza navbar-fixed-top">
    <div class="container-fluid">
        <div class="navbar-header">
            <a class="navbar-brand" href="#" style="    padding: 5px 5px 0px;">
                <img src="logo_fireops_h.png" style="height:100%"> 
            </a>
        </div>
        <ul class="nav navbar-nav navbar-right">
            <li>
                <a href="#">
					<span class="status-chip">
						<i class="fa fa-bolt"></i> Akcja: <strong id="header-action-id">-</strong>
					</span> 
                </a>
            </li>
            <li>
                <a href="#">
                    <span class="status-chip">
                        <i class="fa fa-building"></i> Jednostki: <strong id="header-units">-</strong>
                    </span>
                </a>
            </li>
            <li>
                <a href="#">
                    <span class="status-chip">
                        <i class="fa fa-users"></i> Strażacy online: <strong id="header-online">0</strong>
                    </span>
                </a>
            </li>
            <li>
                <a href="#">
                    <span class="status-chip">
                        <i class="fa fa-clock-o"></i> Ostatnia aktualizacja: <strong id="header-updated">-</strong>
                    </span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<div class="container-fluid main-container">
    <div class="row" style="height:100%;">
        <!-- MAPA -->
        <div class="col-sm-8" id="map-container">
            <div id="map"></div>
        </div>

        <!-- PANEL BOCZNY -->
		<div class="col-sm-4" id="sidebar">
			<div class="sidebar-header">
				<h4 style="display:flex; align-items:center; justify-content:space-between;">
					<span>
						<img src="strazak_icon.png" class="firefighter-icon-small" alt="Strażak">
						Panel akcji
					</span>
					<div class="btn-group btn-group-xs">
						<button id="toggle-compact" class="btn btn-default active" title="Widok kompaktowy">
							<i class="fa fa-th-list"></i>
						</button>
						<button id="toggle-full" class="btn btn-default" title="Widok pełny">
							<i class="fa fa-align-justify"></i>
						</button>
						<button id="toggle-filters" class="btn btn-default" title="Filtry">
							<i class="fa fa-sliders"></i>
						</button>
					</div>
				</h4>
				<small>Na żywo z systemu telemetrycznego </small>



				<!-- Zakładki: Strażacy / Alerty -->
				<ul class="nav nav-pills" style="margin-top:10px;">
					<li class="active">
						<a href="#" id="tab-firefighters">
							<i class="fa fa-user"></i> Strażacy
						</a>
					</li>
					<li>
						<a href="#" id="tab-alerts">
							<i class="fa fa-exclamation-triangle"></i> Alerty
						</a>
					</li>
				</ul>
			</div>

			<!-- FILTRY STRAŻAKÓW (schowane domyślnie) -->
			<div id="firefighters-filters" style="margin-bottom:10px;">
				<div class="form-group">
					<label for="filter-id-name">
						<i class="fa fa-search"></i> ID / Imię i nazwisko
					</label>
					<input type="text" class="form-control input-sm" id="filter-id-name"
						   placeholder="np. FF-01, Jan">
				</div>
				<div class="form-group">
					<label for="filter-team">
						<i class="fa fa-users"></i> Zespół
					</label>
					<input type="text" class="form-control input-sm" id="filter-team"
						   placeholder="np. Alfa, A-1">
				</div>
				<div class="form-group">
					<label for="filter-action">
						<i class="fa fa-bolt"></i> Akcja
					</label>
					<input type="text" class="form-control input-sm" id="filter-action"
						   placeholder="np. ACT-1234">
				</div>
				<div class="form-inline">
					<div class="form-group" style="margin-right:10px;">
						<label for="filter-status">Status</label>
						<select class="form-control input-sm" id="filter-status">
							<option value="">dowolny</option>
							<option value="IN_ACTION">w akcji</option>
							<option value="EN_ROUTE">w drodze</option>
							<option value="STANDBY">w gotowości</option>
							<option value="ALERT">ALERT</option>
							<option value="OFFLINE">OFFLINE</option>
						</select>
					</div>
					<div class="form-group">
						<label for="filter-battery">Bateria</label>
						<select class="form-control input-sm" id="filter-battery">
							<option value="">dowolna</option>
							<option value="20">&lt; 20%</option>
							<option value="40">&lt; 40%</option>
							<option value="60">&lt; 60%</option>
						</select>
					</div>
				</div>
			</div>

			<!-- LISTA STRAŻAKÓW -->
			<div id="firefighters-list">
				<!-- wypełniane z JS -->
			</div>

			<!-- PANEL ALERTÓW -->
			<div id="alerts-panel" style="display:none;">
				<div id="alerts-filters" style="margin-bottom:10px;">
					<div class="form-group">
						<label for="filter-alert-text">
							<i class="fa fa-search"></i> Filtr alertów (nazwa / strażak / zespół / akcja)
						</label>
						<input type="text" class="form-control input-sm" id="filter-alert-text"
							   placeholder="np. MAN-DOWN, FF-01, Alfa, ACT-1234">
					</div>
				</div>
				<div id="alerts-list">
					<!-- lista alertów z JS -->
				</div>
			</div>
		</div>

    </div>
</div>

<!-- jQuery -->
<script 
    src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.3/jquery.min.js">
</script>
<!-- Bootstrap JS -->
<script 
    src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js">
</script>
<!-- Leaflet JS -->
<script 
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin="">
</script>
<script type="text/javascript">

var isCompactView = true; // domyślnie widok kompaktowy
var currentAlerts = [];          // lista wszystkich bieżących alertów
var lastServerTimestamp = null;  // do "jak dawno"
var mapAutoZoomDone = false;
// Ikona standardowa
var firefighterIcon = L.icon({
    iconUrl: 'strazak_icon.png',
    iconSize: [40, 48],
    iconAnchor: [20, 44],
    popupAnchor: [0, -40]
});

// Ikona specjalna dla FF-100
var firefighterIconBlue = L.icon({
    iconUrl: 'strazak_ikona_blue.png',
    iconSize: [40, 48],
    iconAnchor: [20, 44],
    popupAnchor: [0, -40]
});

$(function () {

    var API_URL = 'api/firefighters_proxy.php';

    // --- MAPA + WARSTWY PODKŁADOWE ---

    var osmLayer = L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }
    );

    var esriSatLayer = L.tileLayer(
        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{x}/{y}',
        {
            maxZoom: 19,
            attribution: '&copy; Esri'
        }
    );

    var darkLayer = L.tileLayer(
        'https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',
        {
            maxZoom: 24,
            attribution: '&copy; Carto'
        }
    );

    var map = L.map('map', {
        center: [52.0, 19.0],
        zoom: 24,
        layers: [darkLayer]
    });

    var baseLayers = {
        "Mapa standardowa (OSM)": osmLayer,
        "Satelita": esriSatLayer,
        "Tryb nocny": darkLayer
    };

    L.control.layers(baseLayers, null, {position: 'topright'}).addTo(map);

    // Ikona strażaka
    var firefighterIcon = L.icon({
        iconUrl: 'strazak_icon.png',
        iconSize: [40, 48],
        iconAnchor: [20, 44],
        popupAnchor: [0, -40]
    });

    // --- STRUKTURY DANYCH ---

    var firefighterMarkers   = {};   // id -> marker
    var firefighterHistory   = {};   // id -> [LatLng...]
    var firefighterPaths     = {};   // id -> polyline
    var MAX_HISTORY_POINTS   = 200;

    var firefighterVitalsHistory = {}; // id -> {ts:[], heart:[], battery:[]}
    var MAX_VITAL_HISTORY_POINTS = 300;

    var currentFirefighters  = [];   // pełne dane z API (ostatnia odpowiedź)
    var currentAlerts        = [];   // „spłaszczone” alerty
    var lastServerTimestamp  = null; // timestamp z API
    var activeTab            = 'firefighters';

    // --- FUNKCJE POMOCNICZE ---

    function formatTime(ts) {
        var d = new Date(ts * 1000);
        var hh = ('0' + d.getHours()).slice(-2);
        var mm = ('0' + d.getMinutes()).slice(-2);
        var ss = ('0' + d.getSeconds()).slice(-2);
        return hh + ':' + mm + ':' + ss;
    }

    function formatRelativeSeconds(secs) {
        if (secs < 0) secs = 0;
        if (secs < 60) {
            return secs + ' s temu';
        }
        var m = Math.floor(secs / 60);
        var s = secs % 60;
        return m + ' min ' + s + ' s temu';
    }

    function updateHeader(data) {
        $('#header-action-id').text(data.action_id);
        $('#header-units').text(data.units);
        $('#header-online').text(data.firefighters_online);
        $('#header-updated').text(formatTime(data.timestamp));
    }
function buildAlertsText(alerts) {
    if (!alerts || alerts.length === 0) {
        return 'brak';
    }
    var labels = [];
    for (var i = 0; i < alerts.length; i++) {
        var a = alerts[i];
        labels.push(a.label || a.type);
    }
    return labels.join(', ');
}


	function buildFirefighterCardCompact(f) {
		var alerts      = f.alerts || f.events || [];
		var alertsText  = buildAlertsText(alerts);
		var secondsStill = f.seconds_still || 0;
		var steps        = f.steps || 0;

		var status = f.status || '';
		var statusChipClass = 'ff-status-chip-ok';
		if (status === 'ALERT') {
			statusChipClass = 'ff-status-chip-alert';
		} else if (status === 'OFFLINE') {
			statusChipClass = 'ff-status-chip-offline';
		}

		var html = '' +
			'<div class="firefighter-card compact" data-id="' + f.id + '">' +
				'<div class="ff-row-top">' +
					'<div>' +
						'<span class="ff-icon ff-icon-main"><i class="fa fa-user"></i></span>' +
						'<span class="ff-name">' + f.name + '</span>' +
						'<span class="ff-id">(' + f.id + ')</span>' +
					'</div>' +
					'<div class="ff-status-chip ' + statusChipClass + '">' + status + '</div>' +
				'</div>' +
				'<div class="ff-row-main">' +
					'<span class="ff-icon ff-icon-heart"><i class="fa fa-heartbeat"></i></span>' +
					'<span class="ff-value">' + f.heart_rate + ' bpm</span>' +
					'<span class="ff-separator">·</span>' +
					'<span class="ff-icon ff-icon-thermo"><i class="fa fa-thermometer-half"></i></span>' +
					'<span class="ff-value">' + f.body_temp + '&deg;C / ' + f.ambient_temp + '&deg;C</span>' +
					'<span class="ff-separator">·</span>' +
					'<span class="ff-icon ff-icon-air"><i class="fa fa-tint"></i></span>' +
					'<span class="ff-value">' + f.air_left + '%</span>' +
					'<span class="ff-separator">·</span>' +
					'<span class="ff-icon ff-icon-battery"><i class="fa fa-battery-half"></i></span>' +
					'<span class="ff-value">' + f.battery + '%</span>' +
				'</div>' +
				'<div class="ff-row-main">' +
					'<span class="ff-icon"><i class="fa fa-users"></i></span>' +
					'<span class="ff-value">' + f.team_name + ' (' + f.team_number + ')</span>' +
					'<span class="ff-separator">·</span>' +
					'<span class="ff-icon"><i class="fa fa-bolt"></i></span>' +
					'<span class="ff-value">' + f.action + '</span>' +
				'</div>' +
				'<div class="ff-row-main">' +
					'<span class="ff-icon"><i class="fa fa-pause"></i></span>' +
					'<span class="ff-value">' + secondsStill + ' s</span>' +
					'<span class="ff-separator">·</span>' +
					'<span class="ff-icon"><i class="fa fa-road"></i></span>' +
					'<span class="ff-value">' + steps + '</span>' +
				'</div>' +
				'<div class="ff-row-alerts">' +
					'<span class="ff-icon ff-icon-alert"><i class="fa fa-exclamation-triangle"></i></span>' +
					'<span class="ff-value">' + alertsText + '</span>' +
				'</div>' +
			'</div>';

		return html;
	}

	function buildFirefighterCardFull(f) {
		var isAlert   = (f.status === 'ALERT');
		var isOffline = (f.status === 'OFFLINE');
		var statusClass = isAlert ? 'status-label-alert' : 'status-label-ok';
		if (isOffline) {
			statusClass = 'status-label-alert';
		}

		var alerts      = f.alerts || f.events || [];
		var alertsText  = buildAlertsText(alerts);
		var secondsStill = f.seconds_still || 0;
		var steps        = f.steps || 0;

		var html = '' +
			'<div class="firefighter-card" data-id="' + f.id + '">' +
				'<div class="media">' +
					'<div class="media-left">' +
						'<img src="strazak_icon.png" class="firefighter-icon-small" alt="Strażak">' +
					'</div>' +
					'<div class="media-body">' +
						'<div class="firefighter-name">' +
							'<span class="ff-icon ff-icon-main"><i class="fa fa-user"></i></span>' +
							'<span class="ff-value-strong">' + f.name + '</span> ' +
							'<small class="ff-id">(' + f.id + ')</small>' +
						'</div>' +
						'<div class="firefighter-status compact-row">' +
							'<span class="ff-icon"><i class="fa fa-users"></i></span>' +
							'<span class="ff-value">' + f.team_name + ' (' + f.team_number + ')</span>' +
							'<span class="ff-separator">·</span>' +
							'<span class="ff-icon"><i class="fa fa-bolt"></i></span>' +
							'<span class="ff-value">' + f.action + '</span>' +
						'</div>' +
						'<div class="firefighter-status compact-row">' +
							'<span class="ff-icon"><i class="fa fa-circle"></i></span>' +
							'<span class="ff-value ' + statusClass + '">' + f.status + '</span>' +
						'</div>' +
						'<div class="firefighter-status compact-row">' +
							'<span class="ff-icon ff-icon-heart"><i class="fa fa-heartbeat"></i></span>' +
							'<span class="ff-value">' + f.heart_rate + ' bpm</span>' +
							'<span class="ff-separator">·</span>' +
							'<span class="ff-icon ff-icon-thermo"><i class="fa fa-thermometer-half"></i></span>' +
							'<span class="ff-value">' + f.body_temp + '&deg;C / ' + f.ambient_temp + '&deg;C</span>' +
						'</div>' +
						'<div class="firefighter-status compact-row">' +
							'<span class="ff-icon ff-icon-air"><i class="fa fa-tint"></i></span>' +
							'<span class="ff-value">' + f.air_left + '%</span>' +
							'<span class="ff-separator">·</span>' +
							'<span class="ff-icon ff-icon-battery"><i class="fa fa-battery-half"></i></span>' +
							'<span class="ff-value">' + f.battery + '%</span>' +
						'</div>' +
						'<div class="firefighter-status compact-row">' +
							'<span class="ff-icon"><i class="fa fa-pause"></i></span>' +
							'<span class="ff-value">' + secondsStill + ' s</span>' +
							'<span class="ff-separator">·</span>' +
							'<span class="ff-icon"><i class="fa fa-road"></i></span>' +
							'<span class="ff-value">' + steps + '</span>' +
						'</div>' +
						'<div class="firefighter-status compact-row">' +
							'<span class="ff-icon ff-icon-alert"><i class="fa fa-exclamation-triangle"></i></span>' +
							'<span class="ff-value">' + alertsText + '</span>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';

		return html;
	}
	function getAlertMeta(type) {
		switch (type) {
			case 'man_down':
				return { label: 'MAN-DOWN', severity: 'critical', description: 'Bezruch > 30s' };
			case 'sos_pressed':
				return { label: 'SOS', severity: 'critical', description: 'Przycisk SOS' };
			case 'high_heart_rate':
				return { label: 'Wysokie tętno', severity: 'warning', description: 'Tętno > 180 bpm' };
			case 'low_battery':
				return { label: 'Niska bateria', severity: 'warning', description: 'Bateria < 20%' };
			case 'scba_low_pressure':
				return { label: 'Niskie powietrze', severity: 'warning', description: 'Niskie ciśnienie SCBA' };
			case 'scba_critical':
				return { label: 'Krytyczne powietrze', severity: 'critical', description: 'Krytyczne ciśnienie SCBA' };
			case 'beacon_offline':
				return { label: 'Beacon offline', severity: 'warning', description: 'Beacon nie odpowiada' };
			case 'tag_offline':
				return { label: 'Tag offline', severity: 'critical', description: 'Tag strażaka offline' };
			case 'high_co':
				return { label: 'Wysokie CO', severity: 'critical', description: 'Wysokie stężenie CO' };
			case 'low_oxygen':
				return { label: 'Niski O₂', severity: 'critical', description: 'Niskie stężenie tlenu' };
			case 'explosive_gas':
				return { label: 'Gaz wybuchowy', severity: 'critical', description: 'LEL w strefie wybuchowej' };
			case 'high_temperature':
				return { label: 'Wysoka temperatura', severity: 'warning', description: 'Wysoka temperatura otoczenia' };
			default:
				return { label: type, severity: 'info', description: '' };
		}
	}
function detectAlertsForFirefighter(mapped, raw, nowTs) {
    var alerts = [];

    var vit  = raw.vitals || {};
    var env  = raw.environment || {};
    var dev  = raw.device || {};
    var scba = raw.scba || {};
    var pass = raw.pass_status || {};
    var pos  = raw.position || {};
    var uwb  = raw.uwb_measurements || [];

    // ---- man_down: Bezruch >30s ----
    if (mapped.seconds_still != null && mapped.seconds_still > 30) {
        alerts.push('man_down');
    }

    // ---- sos_pressed ----
    if (dev.sos_button_pressed) {
        alerts.push('sos_pressed');
    }

    // ---- high_heart_rate: > 180 bpm ----
    if (mapped.heart_rate != null && mapped.heart_rate > 180) {
        alerts.push('high_heart_rate');
    }

    // ---- low_battery: <20% ----
    if (mapped.battery != null && mapped.battery < 20) {
        alerts.push('low_battery');
    }

    // ---- SCBA: niskie / krytyczne ciśnienie ----
    if (scba && scba.max_pressure_bar && scba.cylinder_pressure_bar != null) {
        var airPct = (scba.cylinder_pressure_bar / scba.max_pressure_bar) * 100;
        var hasLow  = scba.alarms && scba.alarms.low_pressure;
        var hasCrit = scba.alarms && scba.alarms.very_low_pressure;

        if (airPct <= 10 || hasCrit) {
            alerts.push('scba_critical');
        } else if (airPct <= 25 || hasLow) {
            alerts.push('scba_low_pressure');
        }
    }

    // ---- beacon_offline: brak beaconów / pomiarów ----
    var beaconsUsed = 0;
    if (typeof pos.beacons_used !== 'undefined' && pos.beacons_used !== null) {
        beaconsUsed = pos.beacons_used;
    } else if (uwb && uwb.length) {
        beaconsUsed = uwb.length;
    }
    if (!beaconsUsed || beaconsUsed === 0) {
        alerts.push('beacon_offline');
    }

    // ---- tag_offline: last_sync_cloud starsze niż 10s ----
    if (dev.last_sync_cloud) {
        var diffSec = (nowTs * 1000 - dev.last_sync_cloud) / 1000;
        if (diffSec > 10) {
            alerts.push('tag_offline');
        }
    }

    // ---- high_co ----
    if (env) {
        if (env.co_alarm || (env.co_ppm != null && env.co_ppm > 50)) {
            alerts.push('high_co');
        }
        // ---- low_oxygen ----
        if (env.o2_alarm || (env.o2_percent != null && env.o2_percent < 19.5)) {
            alerts.push('low_oxygen');
        }
        // ---- explosive_gas: LEL ----
        if (env.lel_alarm || (env.lel_percent != null && env.lel_percent >= 10)) {
            alerts.push('explosive_gas');
        }
        // ---- high_temperature ----
        if (env.temperature_alarm || (env.temperature_c != null && env.temperature_c >= 60)) {
            alerts.push('high_temperature');
        }
    }

    // Usunięcie duplikatów
    var unique = [];
    var seen = {};
    for (var i = 0; i < alerts.length; i++) {
        var t = alerts[i];
        if (!seen[t]) {
            seen[t] = true;
            unique.push(t);
        }
    }
    return unique;
}

	// Główna funkcja używana przez updateSidebar
	function buildFirefighterCard(f) {
		if (isCompactView) {
			return buildFirefighterCardCompact(f);
		}
		return buildFirefighterCardFull(f);
	}

    function updateSidebar(filteredFirefighters) {
        var $list = $('#firefighters-list');
        $list.empty();

        if (!filteredFirefighters || filteredFirefighters.length === 0) {
            $list.append('<div class="firefighter-card">Brak danych (lub nic nie pasuje do filtrów).</div>');
            return;
        }

        for (var i = 0; i < filteredFirefighters.length; i++) {
            $list.append(buildFirefighterCard(filteredFirefighters[i]));
        }
    }

    function hasMoved(lastLatLng, newLatLng) {
        if (!lastLatLng) {
            return true;
        }
        var dLat = Math.abs(lastLatLng.lat - newLatLng.lat);
        var dLng = Math.abs(lastLatLng.lng - newLatLng.lng);
        return (dLat > 0.000001 || dLng > 0.000001);
    }

    function updateMarkers(firefighters) {
        for (var i = 0; i < firefighters.length; i++) {
            var f = firefighters[i];
            var latlng = L.latLng(f.lat, f.lng);

            // historia pozycji
            if (!firefighterHistory[f.id]) {
                firefighterHistory[f.id] = [];
            }
            var history   = firefighterHistory[f.id];
            var lastPoint = history.length > 0 ? history[history.length - 1] : null;

            if (hasMoved(lastPoint, latlng)) {
                history.push(latlng);
                if (history.length > MAX_HISTORY_POINTS) {
                    history.shift();
                }
            }

            // marker
            if (firefighterMarkers[f.id]) {
                firefighterMarkers[f.id].setLatLng(latlng);
            } else {
                // wybór ikony
				var iconToUse = (f.id === "FF-100") ? firefighterIconBlue : firefighterIcon;

				// nowy marker
				var marker = L.marker(latlng, { icon: iconToUse });

                var alerts = f.alerts || f.events || [];
                var alertsText = buildAlertsText(alerts);

                marker.bindPopup(
                    '<b>' + f.name + '</b><br>' +
                    'ID: ' + f.id + '<br>' +
                    '<i class="fa fa-users"></i> ' + f.team_name + ' (' + f.team_number + ')<br>' +
                    '<i class="fa fa-bolt"></i> Akcja: ' + f.action + '<br>' +
                    '<i class="fa fa-heartbeat"></i> Tętno: ' + f.heart_rate + ' bpm<br>' +
                    'Temp ciała: ' + f.body_temp + '&deg;C, ' +
                    'Temp otoczenia: ' + f.ambient_temp + '&deg;C<br>' +
                    '<i class="fa fa-tint"></i> Powietrze: ' + f.air_left + '%, ' +
                    '<i class="fa fa-battery-half"></i> Bateria: ' + f.battery + '%<br>' +
                    '<i class="fa fa-pause"></i> Bez ruchu: ' + f.seconds_still + ' s, ' +
                    '<i class="fa fa-road"></i> Kroki: ' + f.steps + '<br>' +
                    '<i class="fa fa-exclamation-triangle"></i> Alerty: ' + alertsText
                );
                marker.addTo(map);
                firefighterMarkers[f.id] = marker;
            }

            // ścieżka ruchu
            if (!firefighterPaths[f.id]) {
                var poly = L.polyline(history, {
                    color: '#ff9800',
                    weight: 3,
                    opacity: 0.8
                });
                poly.addTo(map);
                firefighterPaths[f.id] = poly;
            } else {
                firefighterPaths[f.id].setLatLngs(history);
            }
        }
    }

    // --- HISTORIA VITALS (tętno + bateria) ---

    function updateVitalsHistoryForFirefighter(f, ts) {
        var id = f.id;
        if (!firefighterVitalsHistory[id]) {
            firefighterVitalsHistory[id] = { ts: [], heart: [], battery: [] };
        }
        var h = firefighterVitalsHistory[id];
        h.ts.push(ts);
        h.heart.push(f.heart_rate);
        h.battery.push(f.battery);

        if (h.ts.length > MAX_VITAL_HISTORY_POINTS) {
            h.ts.shift();
            h.heart.shift();
            h.battery.shift();
        }
    }

    function updateVitalsFromData(data) {
        if (!data || !data.firefighters) return;
        for (var i = 0; i < data.firefighters.length; i++) {
            updateVitalsHistoryForFirefighter(data.firefighters[i], data.timestamp);
        }
    }

    // --- FILTROWANIE STRAŻAKÓW ---

    function applyFiltersAndRenderFirefighters() {
        var idName   = $('#filter-id-name').val().toLowerCase();
        var teamText = $('#filter-team').val().toLowerCase();
        var actionText = $('#filter-action').val().toLowerCase();
        var statusFilter  = $('#filter-status').val();
        var batteryFilter = $('#filter-battery').val(); // "", "20", "40", "60"

        var filtered = [];

        for (var i = 0; i < currentFirefighters.length; i++) {
            var f = currentFirefighters[i];

            var ok = true;

            if (idName) {
                var combined = (f.id + ' ' + f.name).toLowerCase();
                if (combined.indexOf(idName) === -1) {
                    ok = false;
                }
            }

            if (ok && teamText) {
                var teamCombined = (f.team_name + ' ' + f.team_number).toLowerCase();
                if (teamCombined.indexOf(teamText) === -1) {
                    ok = false;
                }
            }

            if (ok && actionText) {
                if (!f.action || f.action.toLowerCase().indexOf(actionText) === -1) {
                    ok = false;
                }
            }

            if (ok && statusFilter) {
                if (f.status !== statusFilter) {
                    ok = false;
                }
            }

            if (ok && batteryFilter) {
                var threshold = parseInt(batteryFilter, 10);
                if (f.battery >= threshold) {
                    ok = false;
                }
            }

            if (ok) {
                filtered.push(f);
            }
        }

        updateSidebar(filtered);
    }

    // --- LISTA ALERTÓW ---

function rebuildAlertsFromData(data) {
    currentAlerts = data.alerts || [];
}

    function buildAlertRow(a, nowTs) {
        var secondsAgo = nowTs - a.ts;
        if (secondsAgo < 0) secondsAgo = 0;
        var rel = formatRelativeSeconds(secondsAgo);

        var html = '' +
            '<div class="firefighter-card alert-card" data-id="' + a.firefighter_id + '">' +
                '<div class="firefighter-name">' +
                    '<i class="fa fa-exclamation-triangle"></i> ' + a.code +
                    ' <small>(' + a.firefighter_id + ')</small>' +
                '</div>' +
                '<div class="firefighter-status">' +
                    '<i class="fa fa-user"></i> ' + a.firefighter_name +
                '</div>' +
                '<div class="firefighter-status">' +
                    '<i class="fa fa-users"></i> ' + a.team_name + ' (' + a.team_number + ')' +
                '</div>' +
                '<div class="firefighter-status">' +
                    '<i class="fa fa-bolt"></i> Akcja: ' + a.action +
                '</div>' +
                '<div class="firefighter-status">' +
                    '<i class="fa fa-clock-o"></i> ' + rel +
                '</div>' +
            '</div>';

        return html;
    }

function renderAlertsList() {
    var $list = $('#alerts-list');
    $list.empty();

    if (!currentAlerts || currentAlerts.length === 0) {
        $list.append('<div class="alert-card">Brak aktywnych alertów.</div>');
        return;
    }

    var filterText = $('#filter-alert-text').val() || '';
    filterText = filterText.toLowerCase();

    var nowSec = Math.floor(Date.now() / 1000);

    for (var i = 0; i < currentAlerts.length; i++) {
        var a = currentAlerts[i];

        var composite = (
            (a.label || '') + ' ' +
            (a.type || '') + ' ' +
            (a.firefighter_name || '') + ' ' +
            (a.team_name || '') + ' ' +
            (a.action || '')
        ).toLowerCase();

        if (filterText && composite.indexOf(filterText) === -1) {
            continue;
        }

        var agoSec = nowSec - (a.ts || nowSec);
        var agoText = typeof formatRelativeSeconds === 'function'
            ? formatRelativeSeconds(agoSec)
            : (agoSec + ' s temu');

        var sevClass = (a.severity === 'critical')
            ? 'alert-chip-critical'
            : (a.severity === 'warning' ? 'alert-chip-warning' : 'alert-chip-info');

        var html = '' +
            '<div class="alert-card" data-id="' + a.firefighter_id + '">' +
                '<div class="alert-card-header">' +
                    '<span class="alert-label ' + sevClass + '">' +
                        '<i class="fa fa-exclamation-triangle"></i> ' + a.label +
                    '</span>' +
                    '<span class="alert-time">' + agoText + '</span>' +
                '</div>' +
                '<div class="alert-card-body">' +
                    '<div><i class="fa fa-user"></i> ' + a.firefighter_name + ' (' + a.firefighter_id + ')</div>' +
                    '<div><i class="fa fa-users"></i> ' + a.team_name + '</div>' +
                    '<div><i class="fa fa-bolt"></i> ' + a.action + '</div>' +
                    (a.description ? '<div class="alert-desc">' + a.description + '</div>' : '') +
                '</div>' +
            '</div>';

        $list.append(html);
    }

    if ($list.children().length === 0) {
        $list.append('<div class="alert-card">Brak alertów dla podanego filtra.</div>');
    }
}

    // --- RYSOWANIE WYKRESÓW W MODALU ---

    function drawTrendChart(canvasId, dataArr, color) {
        var canvas = document.getElementById(canvasId);
        if (!canvas || !canvas.getContext) {
            return;
        }
        var ctx = canvas.getContext('2d');
        var w = canvas.width;
        var h = canvas.height;

        ctx.clearRect(0, 0, w, h);

        // tło
        ctx.fillStyle = '#202020';
        ctx.fillRect(0, 0, w, h);

        if (!dataArr || dataArr.length < 2) {
            ctx.fillStyle = '#cccccc';
            ctx.font = '10px Arial';
            ctx.fillText('Brak danych do wyświetlenia', 10, h / 2);
            return;
        }

        var minVal = Math.min.apply(null, dataArr);
        var maxVal = Math.max.apply(null, dataArr);
        if (minVal === maxVal) {
            minVal -= 1;
            maxVal += 1;
        }

        var xStep = w / (dataArr.length - 1);

        ctx.strokeStyle = color;
        ctx.lineWidth = 2;
        ctx.beginPath();

        for (var i = 0; i < dataArr.length; i++) {
            var v = dataArr[i];
            var x = i * xStep;
            var norm = (v - minVal) / (maxVal - minVal);
            var y = h - norm * (h - 10) - 5; // marginesy

            if (i === 0) {
                ctx.moveTo(x, y);
            } else {
                ctx.lineTo(x, y);
            }
        }
        ctx.stroke();
    }

    // --- MODAL SZCZEGÓŁÓW STRAŻAKA ---
	var currentModalFirefighterId = null;

	function openFirefighterModal(id, options) {
		options = options || {};
		currentModalFirefighterId = id;

		var f = null;
		for (var i = 0; i < currentFirefighters.length; i++) {
			if (currentFirefighters[i].id === id) {
				f = currentFirefighters[i];
				break;
			}
		}
		if (!f) {
			return;
		}

		$('#modal-name').text(f.name);
		$('#modal-id').text(' (' + f.id + ')');
		$('#firefighterModalLabel').text(f.name + ' (' + f.id + ')');

		$('#modal-team').text(f.team_name + ' (' + f.team_number + ')');
		$('#modal-action').text(f.action);
		$('#modal-status').text(f.status);

		$('#modal-heart-rate').text(f.heart_rate + ' bpm');
		$('#modal-body-temp').text(f.body_temp + ' °C');
		$('#modal-ambient-temp').text(f.ambient_temp + ' °C');
		$('#modal-air-left').text(f.air_left + ' %');
		$('#modal-battery').text(f.battery + ' %');

		$('#modal-steps').text(f.steps);
		$('#modal-seconds-still').text(f.seconds_still + ' s');
		$('#modal-last-position').text(
			f.lat.toFixed(5) + ', ' + f.lng.toFixed(5)
		);

		if (f.last_move_ts && lastServerTimestamp) {
			var diff = lastServerTimestamp - f.last_move_ts;
			$('#modal-last-move').text(formatRelativeSeconds(diff));
		} else {
			$('#modal-last-move').text('brak danych');
		}

		var alerts = f.alerts || f.events || [];
		var $alertList = $('#modal-alerts-list');
		$alertList.empty();
		if (!alerts.length) {
			$alertList.append('<li>Brak alertów</li>');
		} else {
			for (var j = 0; j < alerts.length; j++) {
				$alertList.append('<li>' + alerts[j] + '</li>');
			}
		}

		var h = firefighterVitalsHistory[id];
		var heartArr   = h ? h.heart : null;
		var batteryArr = h ? h.battery : null;

		drawTrendChart('heartTrendCanvas', heartArr, '#ff5252');
		drawTrendChart('batteryTrendCanvas', batteryArr, '#4caf50');

		// Pokaż modal tylko jeśli to wywołanie nie jest "odświeżeniem"
		if (!options.skipShow) {
			$('#firefighterModal').modal('show');
		}
	}

    // --- POBIERANIE DANYCH ---
function adaptApiData(rawData) {
    var list = (rawData && rawData.firefighters) ? rawData.firefighters : [];
    var nowTs = Math.floor(Date.now() / 1000);
    lastServerTimestamp = nowTs;

    var viewFirefighters = [];
    var teamSet = {};
    currentAlerts = [];

    for (var i = 0; i < list.length; i++) {
        var item  = list[i] || {};
        var ff    = item.firefighter || {};
        var vit   = item.vitals || {};
        var env   = item.environment || {};
        var dev   = item.device || {};
        var scba  = item.scba || {};
        var pass  = item.pass_status || {};
        var posGps = null;

        if (item.position && item.position.gps) {
            posGps = item.position.gps;
        } else if (item.gps) {
            posGps = item.gps;
        }

        var tsIso = item.timestamp;
        var ts    = nowTs;

        if (tsIso) {
            var parsed = Date.parse(tsIso);
            if (!isNaN(parsed)) {
                ts = Math.floor(parsed / 1000);
            }
        }

        var secondsStill = 0;
        if (pass.time_since_motion_s != null) {
            secondsStill = pass.time_since_motion_s;
        } else if (vit.stationary_duration_s != null) {
            secondsStill = vit.stationary_duration_s;
        }

        var lastMoveTs = ts - secondsStill;

        var airPercent = 100;
        if (scba.max_pressure_bar && scba.cylinder_pressure_bar != null) {
            airPercent = Math.round(
                (scba.cylinder_pressure_bar / scba.max_pressure_bar) * 100
            );
        }

        var status = 'STANDBY';
        if (pass.status === 'active') {
            status = 'IN_ACTION';
        }

        var teamName   = ff.team || 'Zespół';
        var teamNumber = ff.team || '';

        var mapped = {
            id: ff.id || ('FF-' + (i + 1)),
            name: ff.name || ('Strażak ' + (i + 1)),
            lat: posGps ? posGps.lat : 52.0,
            lng: posGps ? posGps.lon : 19.0,

            team_name: teamName,
            team_number: teamNumber,

            action: 'LIVE-' + (new Date(ts * 1000).toISOString().slice(0, 10)),

            status: status,
            is_online: true,

            heart_rate: vit.heart_rate_bpm != null ? vit.heart_rate_bpm : 0,
            body_temp: vit.skin_temperature_c != null ? vit.skin_temperature_c : (env.temperature_c || 0),
            ambient_temp: env.temperature_c != null ? env.temperature_c : 0,

            air_left: airPercent,
            battery: dev.battery_percent != null ? dev.battery_percent : 0,

            steps: vit.step_count != null ? vit.step_count : 0,
            seconds_still: secondsStill,
            last_move_ts: lastMoveTs,

            alerts: [],

            // kilka pól „raw” przydatnych w modalu/debugu (opcjonalne)
            _raw_env: {
                co_ppm: env.co_ppm,
                o2_percent: env.o2_percent,
                lel_percent: env.lel_percent
            },
            _raw_scba: {
                cylinder_pressure_bar: scba.cylinder_pressure_bar,
                max_pressure_bar: scba.max_pressure_bar
            },
            _raw_device: {
                last_sync_cloud: dev.last_sync_cloud
            },
            _timestamp: ts
        };

        // --- DETEKCJA ALERTÓW DLA TEGO STRAŻAKA ---
        var alertTypes = detectAlertsForFirefighter(mapped, item, nowTs);
        var fa = [];
        for (var j = 0; j < alertTypes.length; j++) {
            var t   = alertTypes[j];
            var def = getAlertMeta(t);
            var alertObj = {
                firefighter_id: mapped.id,
                firefighter_name: mapped.name,
                team_name: mapped.team_name,
                team_number: mapped.team_number,
                action: mapped.action,
                type: t,
                label: def.label,
                severity: def.severity,
                description: def.description,
                ts: ts
            };
            fa.push(alertObj);
            currentAlerts.push(alertObj);
        }
        mapped.alerts = fa;

        viewFirefighters.push(mapped);

        if (teamName) {
            teamSet[teamName] = true;
        }
    }

    var header = {
        timestamp: nowTs,
        action_id: 'LIVE-BUILDING',
        units: Object.keys(teamSet).length,
        firefighters_online: viewFirefighters.length,
        firefighters: viewFirefighters,
        alerts: currentAlerts
    };

    return header;
}

function fetchData() {
    $.getJSON(API_URL, function (rawData) {
        var data = adaptApiData(rawData);
        if (!data) {
            return;
        }

        updateHeader(data);

        currentFirefighters = data.firefighters || [];

        updateVitalsFromData(data);
        rebuildAlertsFromData(data);

        applyFiltersAndRenderFirefighters();
        renderAlertsList();
        updateMarkers(currentFirefighters);
		if (!mapAutoZoomDone && currentFirefighters.length > 0) {
			tryAutoFitMap(currentFirefighters);
			mapAutoZoomDone = true;
		}
        if (typeof currentModalFirefighterId !== 'undefined' &&
            currentModalFirefighterId &&
            $('#firefighterModal').hasClass('in')) {
            openFirefighterModal(currentModalFirefighterId, { skipShow: true });
        }
    }).fail(function () {
        console.error('Błąd pobierania danych z ' + API_URL);
    });
}
function tryAutoFitMap(firefighters) {
    var bounds = [];

    for (var i = 0; i < firefighters.length; i++) {
        var f = firefighters[i];
        if (f.lat && f.lng) {
            bounds.push([f.lat, f.lng]);
        }
    }

    if (bounds.length === 0) return;

    var latLngBounds = L.latLngBounds(bounds);

    // użyj minimalnego i maksymalnego wygodnego zoomu
    map.fitBounds(latLngBounds, {
        padding: [50, 50],
        maxZoom: 22
    });
}


    // --- ZDARZENIA UI ---
	// Przycisk pokazujący / chowający filtry
	$('#toggle-filters').on('click', function (e) {
		e.preventDefault();
		$('#firefighters-filters').slideToggle(150);
	});

	// Przełączniki widoku: kompaktowy vs pełny
	$('#toggle-compact').on('click', function (e) {
		e.preventDefault();
		isCompactView = true;
		$('#toggle-compact').addClass('active');
		$('#toggle-full').removeClass('active');
		applyFiltersAndRenderFirefighters();
	});

	$('#toggle-full').on('click', function (e) {
		e.preventDefault();
		isCompactView = false;
		$('#toggle-full').addClass('active');
		$('#toggle-compact').removeClass('active');
		applyFiltersAndRenderFirefighters();
	});
	
	
	$('#firefighterModal').on('hidden.bs.modal', function () {
		currentModalFirefighterId = null;
	});	
	
	// Przycisk pokazujący / chowający filtry strażaków
	$('#toggle-filters').on('click', function (e) {
		e.preventDefault();
		$('#firefighters-filters').slideToggle(150);
	});
	
    // Kliknięcie w kartę strażaka – przybliż na mapie + modal
    $('#firefighters-list').on('click', '.firefighter-card', function () {
        var id = $(this).data('id');
        if (id && firefighterMarkers[id]) {
            var marker = firefighterMarkers[id];
            map.setView(marker.getLatLng(), 15);
            marker.openPopup();
        }
        if (id) {
            openFirefighterModal(id);
        }
    });

    // Kliknięcie w alert – mapa + modal
    $('#alerts-list').on('click', '.alert-card', function () {
        var id = $(this).data('id');
        if (id && firefighterMarkers[id]) {
            var marker = firefighterMarkers[id];
            $('#tab-firefighters').parent().addClass('active');
            $('#tab-alerts').parent().removeClass('active');
            $('#firefighters-filters, #firefighters-list').show();
            $('#alerts-panel').hide();
            map.setView(marker.getLatLng(), 15);
            marker.openPopup();
        }
        if (id) {
            openFirefighterModal(id);
        }
    });

    // Zakładki
    $('#tab-firefighters').on('click', function (e) {
        e.preventDefault();
        activeTab = 'firefighters';
        $(this).parent().addClass('active');
        $('#tab-alerts').parent().removeClass('active');
        $('#firefighters-filters, #firefighters-list').show();
        $('#alerts-panel').hide();
    });

    $('#tab-alerts').on('click', function (e) {
        e.preventDefault();
        activeTab = 'alerts';
        $(this).parent().addClass('active');
        $('#tab-firefighters').parent().removeClass('active');
        $('#firefighters-filters, #firefighters-list').hide();
        $('#alerts-panel').show();
    });

    // Filtry strażaków
    $('#filter-id-name, #filter-team, #filter-action').on('input', function () {
        applyFiltersAndRenderFirefighters();
    });

    $('#filter-status, #filter-battery').on('change', function () {
        applyFiltersAndRenderFirefighters();
    });

    // Filtr alertów
    $('#filter-alert-text').on('input', function () {
        renderAlertsList();
    });

    // start
    fetchData();
    setInterval(fetchData, 1000);

});
</script>


<!-- MODAL: Szczegóły strażaka -->
<div class="modal fade" id="firefighterModal" tabindex="-1" role="dialog" aria-labelledby="firefighterModalLabel">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Zamknij">
          <span aria-hidden="true">&times;</span>
        </button>
		<h4 class="modal-title" id="firefighterModalLabel">
		  <i class="fa fa-user"></i> Szczegóły strażaka
		</h4>
      </div>
		<div class="modal-body">
		  <div class="row">
			<!-- Dane podstawowe -->
			<div class="col-sm-6">
			  <h4>Dane</h4>

			  <p>
				<span class="ff-icon ff-icon-main"><i class="fa fa-user"></i></span>
				<span class="ff-value-strong" id="modal-name"></span>
				<small class="ff-id" id="modal-id"></small>
			  </p>

			  <p class="compact-row">
				<span class="ff-icon"><i class="fa fa-users"></i></span>
				<span class="ff-value" id="modal-team"></span>
			  </p>

			  <p class="compact-row">
				<span class="ff-icon"><i class="fa fa-bolt"></i></span>
				<span class="ff-value" id="modal-action"></span>
			  </p>

			  <p class="compact-row">
				<span class="ff-icon"><i class="fa fa-circle"></i></span>
				<span class="ff-value" id="modal-status"></span>
			  </p>

			  <p class="compact-row">
				<span class="ff-icon"><i class="fa fa-map-marker"></i></span>
				<span class="ff-value" id="modal-last-position"></span>
			  </p>

			  <p class="compact-row">
				<span class="ff-icon"><i class="fa fa-history"></i></span>
				<span class="ff-value">Ostatni ruch: <span id="modal-last-move"></span></span>
			  </p>

			  <p class="compact-row">
				<span class="ff-icon"><i class="fa fa-pause"></i></span>
				<span class="ff-value">Bez ruchu: <span id="modal-seconds-still"></span></span>
				<span class="ff-separator">·</span>
				<span class="ff-icon"><i class="fa fa-road"></i></span>
				<span class="ff-value">Kroki: <span id="modal-steps"></span></span>
			  </p>
			</div>

			<!-- Dane biometryczne + alerty -->
			<div class="col-sm-6">
			  <h4>Parametry</h4>

			  <p class="compact-row">
				<span class="ff-icon ff-icon-heart"><i class="fa fa-heartbeat"></i></span>
				<span class="ff-value">Tętno: <span id="modal-heart-rate"></span></span>
			  </p>

			  <p class="compact-row">
				<span class="ff-icon ff-icon-thermo"><i class="fa fa-thermometer-half"></i></span>
				<span class="ff-value">Ciało / otoczenie:
				  <span id="modal-body-temp"></span> / <span id="modal-ambient-temp"></span>
				</span>
			  </p>

			  <p class="compact-row">
				<span class="ff-icon ff-icon-air"><i class="fa fa-tint"></i></span>
				<span class="ff-value">Powietrze: <span id="modal-air-left"></span></span>
				<span class="ff-separator">·</span>
				<span class="ff-icon ff-icon-battery"><i class="fa fa-battery-half"></i></span>
				<span class="ff-value">Bateria: <span id="modal-battery"></span></span>
			  </p>

			  <h5 style="margin-top:15px;">
				<i class="fa fa-exclamation-triangle"></i> Ostatnie alerty
			  </h5>
			  <ul id="modal-alerts-list"></ul>
			</div>
		  </div>

		  <hr>

		  <!-- Trendy -->
		  <div class="row">
			<div class="col-sm-6">
			  <h5><i class="fa fa-line-chart"></i> Trend tętna</h5>
			  <canvas id="heartTrendCanvas" width="350" height="120" style="width:100%;"></canvas>
			</div>
			<div class="col-sm-6">
			  <h5><i class="fa fa-battery-full"></i> Trend baterii</h5>
			  <canvas id="batteryTrendCanvas" width="350" height="120" style="width:100%;"></canvas>
			</div>
		  </div>
		</div>
  
	  
	  <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">
          Zamknij
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  /* lekki dark-mode dla modala */
  .modal-content {
    background-color: #1f1f1f;
    color: #f5f5f5;
  }
  .modal-header, .modal-footer {
    border-color: #333;
  }
  #heartTrendCanvas, #batteryTrendCanvas {
    background-color: #202020;
    border-radius: 4px;
  }
</style>

</body>
</html>
