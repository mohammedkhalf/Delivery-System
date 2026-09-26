@extends('layouts.admin.app')

@section('title',translate('Update Zone'))

@push('css_or_js')

@endpush

@section('content')

    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/edit.png')}}" class="w--26" alt="">
                </span>
                <span>
                   {{ translate('edit_zone')}}
                </span>
            </h1>
        </div>
        <!-- End Page Header -->
        <form action="{{route('admin.business-settings.zone.update', $zone->id)}}" method="post" id="zone_form" class="shadow--card">
            @csrf
            <div class="row">
                <div class="col-md-5">
                    <div class="zone-setup-instructions">
                        <div class="zone-setup-top">
                            <h6 class="subtitle">{{ translate('Instructions') }}</h6>
                            <p>
                                {{ translate('Create_&_connect_dots_in_a_specific_area_on_the_map_to_add_a_new_business_zone.') }}
                            </p>
                        </div>
                        <div class="zone-setup-item">
                            <div class="zone-setup-icon">
                                <i class="tio-hand-draw"></i>
                            </div>
                            <div class="info">
                                {{ translate('Use_this_‘Hand_Tool’_to_find_your_target_zone.') }}
                            </div>
                        </div>
                        <div class="zone-setup-item">
                            <div class="zone-setup-icon">
                                <i class="tio-free-transform"></i>
                            </div>
                            <div class="info">
                                {{ translate('Use_this_‘Shape_Tool’_to_point_out_the_areas_and_connect_the_dots._Minimum_3_points/dots_are_required.') }}
                            </div>
                        </div>
                        <div class="instructions-image mt-4">
                            <img src="{{asset('public/assets/admin/img/instructions.gif')}}" alt="instructions">
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-xl-7 zone-setup">
                    <div class="form-group">
                        @if($language)
                            <ul class="nav nav-tabs mb-4">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active"
                                    href="#"
                                    id="default-link">{{translate('messages.default')}}</a>
                                </li>
                                @foreach ($language as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link"
                                            href="#"
                                            id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    <div class="pl-xl-5 pl-xxl-0">
                        @if($language)
                            <div class="row lang_form" id="default-form">
                                <div class="form-group col-6">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{ translate('messages.default') }})</label>
                                    <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.new_zone')}}" maxlength="191" value="{{$zone?->getRawOriginal('name')}}"  >
                                </div>
                                <div class="form-group col-6">
                                    <label class="input-label" for="exampleFormControlInput1">{{translate('messages.display_name')}} ({{ translate('messages.default') }})</label>
                                    <input type="text" name="display_name[]" class="form-control" placeholder="{{translate('messages.display_name')}}" maxlength="191" value="{{$zone?->getRawOriginal('display_name')}}"  >
                                </div>
                                <input type="hidden" name="lang[]" value="default">
                            </div>
                                @foreach($language as $lang)
                                    <?php
                                        if(count($zone['translations'])){
                                            $translate = [];
                                            foreach($zone['translations'] as $t)
                                            {
                                                if($t->locale == $lang && $t->key=="name"){
                                                    $translate[$lang]['name'] = $t->value;
                                                }
                                                if($t->locale == $lang && $t->key=="display_name"){
                                                    $translate[$lang]['display_name'] = $t->value;
                                                }
                                            }
                                        }
                                    ?>
                                <div class="row lang_form d-none" id="{{$lang}}-form">
                                    <div class="form-group col-6">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.new_zone')}}" maxlength="191" value="{{$translate[$lang]['name']??''}}"  >
                                    </div>
                                    <div class="form-group col-6">
                                        <label class="input-label" for="exampleFormControlInput1">{{translate('messages.display_name')}} ({{strtoupper($lang)}})</label>
                                        <input type="text" name="display_name[]" class="form-control" placeholder="{{translate('messages.display_name')}}" maxlength="191" value="{{$translate[$lang]['display_name']??''}}"  >
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{$lang}}">
                                </div>
                                @endforeach
                            @endif
                        <div class="form-group d-none">
                            <label class="input-label" for="exampleFormControlInput1">{{ translate('messages.Coordinates') }}
                                <span class="form-label-secondary" data-toggle="tooltip" data-placement="right" data-original-title="{{translate('messages.draw_your_zone_on_the_map')}}">
                                    {{translate('messages.draw_your_zone_on_the_map')}}
                                </span>
                            </label>
                                <textarea type="text" rows="8" name="coordinates" id="coordinates" class="form-control" readonly>@foreach($zone->coordinates[0]->toArray()['coordinates'] as $key=>$coords)<?php if(count($zone->coordinates[0]->toArray()['coordinates']) != $key+1) {if($key != 0) echo(','); ?>({{$coords[1]}}, {{$coords[0]}})<?php } ?>@endforeach</textarea>
                        </div>


                        <div class="map-warper rounded mt-0">
                            <input id="pac-input" class="controls rounded initial--33" title="{{translate('messages.search_your_location_here')}}" type="text" placeholder="{{translate('messages.search_here')}}"/>
                            <div id="map-canvas" class="initial--34"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="btn--container mt-3 justify-content-end">
                <button id="reset_btn" type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                <button type="submit" class="btn btn--primary">{{translate('messages.Save_changes')}}</button>
            </div>
        </form>
    </div>

@endsection

@push('script_2')
<script async
    src="https://maps.googleapis.com/maps/api/js?key={{\App\Models\BusinessSetting::where('key', 'map_api_key')->first()->value}}&libraries=places,marker&v=quarterly&loading=async&callback=initialize"></script>
<script>
    "use strict";
    auto_grow();
    function auto_grow() {
        let element = document.getElementById("coordinates");
        element.style.height = "5px";
        element.style.height = (element.scrollHeight)+"px";
    }

    let map;
    let lat_longs = new Array();
    let drawingPolyline = null;
    let drawingPolygon = null;
    let polygonClosed = false;
    let lastpolygon = null;
    // Assigned inside initialize() — `google` isn't defined at parse time
    // when Maps is loaded via &loading=async.
    let bounds = null;
    let polygons = [];
    let drawingMode = false;
    let vertexMarkers = [];
    const MIN_VERTICES = 3;

    // translateY(50%) compensates for AdvancedMarkerElement's bottom-center
    // anchor so the circle's center sits on the LatLng.
    function vertexElement(highlighted) {
        const size = highlighted ? 20 : 12;
        const div = document.createElement('div');
        div.style.cssText =
            'width:' + size + 'px;' +
            'height:' + size + 'px;' +
            'border-radius:50%;' +
            'background:' + (highlighted ? '#00b35c' : '#FF0000') + ';' +
            'border:2px solid #fff;' +
            'box-shadow:0 1px 3px rgba(0,0,0,0.3);' +
            'cursor:' + (highlighted ? 'pointer' : 'default') + ';' +
            'transform:translateY(50%);';
        return div;
    }

    function currentPath() {
        if (polygonClosed && drawingPolygon) return drawingPolygon.getPath().getArray();
        if (drawingPolyline) return drawingPolyline.getPath().getArray();
        return [];
    }

    function syncVertexMarkers() {
        // AdvancedMarkerElement unmounts via `.map = null`, not setMap().
        vertexMarkers.forEach(function (m) { m.map = null; });
        vertexMarkers = [];
        // After close, the editable Polygon draws its own vertex handles.
        if (polygonClosed) return;
        const { AdvancedMarkerElement } = google.maps.marker;
        const path = currentPath();
        path.forEach(function (latLng, idx) {
            const isFirst = idx === 0;
            const canClose = isFirst && path.length >= MIN_VERTICES;
            const marker = new AdvancedMarkerElement({
                position: latLng,
                map: map,
                content: vertexElement(canClose),
                gmpClickable: canClose,
                title: canClose ? "{{ translate('Click_to_close_polygon') }}" : "",
                zIndex: 9999,
            });
            // AdvancedMarkerElement fires 'gmp-click', not 'click'.
            if (canClose) marker.addListener("gmp-click", closePolygon);
            vertexMarkers.push(marker);
        });
    }

    function clearDrawing() {
        if (drawingPolygon) {
            drawingPolygon.setMap(null);
            drawingPolygon = null;
        }
        if (drawingPolyline) {
            drawingPolyline.getPath().clear();
            drawingPolyline.setMap(map);
            lastpolygon = drawingPolyline;
        }
        polygonClosed = false;
        vertexMarkers.forEach(function (m) { m.map = null; });
        vertexMarkers = [];
        $('#coordinates').val('');
        auto_grow();
    }

    function updateCoordinates() {
        const path = currentPath();
        $('#coordinates').val(path.length ? path.toString() : '');
        auto_grow();
        syncVertexMarkers();
    }

    function closePolygon() {
        if (!drawingPolyline) return;
        const path = drawingPolyline.getPath().getArray();
        if (path.length < MIN_VERTICES) return;

        drawingPolyline.setMap(null);
        drawingPolygon = new google.maps.Polygon({
            map: map,
            paths: path,
            editable: true,
            clickable: false,
            strokeColor: "#FF0000",
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: "#FF0000",
            fillOpacity: 0.1,
        });
        polygonClosed = true;
        lastpolygon = drawingPolygon;

        const polyPath = drawingPolygon.getPath();
        google.maps.event.addListener(polyPath, "set_at", updateCoordinates);
        google.maps.event.addListener(polyPath, "insert_at", updateCoordinates);
        google.maps.event.addListener(polyPath, "remove_at", updateCoordinates);

        vertexMarkers.forEach(function (m) { m.map = null; });
        vertexMarkers = [];
        updateCoordinates();
    }

    let handToolEl = null;
    let shapeToolEl = null;

    function setDrawingMode(drawing) {
        drawingMode = drawing;
        if (map) {
            map.setOptions({ draggableCursor: drawing ? "crosshair" : null });
        }
        if (shapeToolEl) {
            shapeToolEl.style.backgroundColor = drawing ? "#e7f0ff" : "#fff";
            shapeToolEl.style.color = drawing ? "#050df2" : "#444";
        }
        if (handToolEl) {
            handToolEl.style.backgroundColor = drawing ? "#fff" : "#e7f0ff";
            handToolEl.style.color = drawing ? "#444" : "#050df2";
        }
    }

    function buildDrawingControl() {
        const wrapper = document.createElement("div");
        wrapper.style.cssText = "margin:10px;display:flex;border-radius:4px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.3);background:#fff;font-family:Roboto,Arial,sans-serif;";

        handToolEl = document.createElement("div");
        handToolEl.title = "Hand Tool — pan the map";
        handToolEl.style.cssText = "cursor:pointer;display:flex;align-items:center;justify-content:center;width:36px;height:36px;font-size:18px;color:#444;";
        handToolEl.innerHTML = `<i class="tio-hand-draw"></i>`;

        shapeToolEl = document.createElement("div");
        shapeToolEl.title = "Shape Tool — click the map to connect the dots";
        shapeToolEl.style.cssText = "cursor:pointer;display:flex;align-items:center;justify-content:center;width:36px;height:36px;font-size:18px;color:#444;border-left:1px solid #e6e6e6;";
        shapeToolEl.innerHTML = `<i class="tio-free-transform"></i>`;

        handToolEl.addEventListener("click", function () { setDrawingMode(false); });
        shapeToolEl.addEventListener("click", function () { setDrawingMode(true); });

        wrapper.appendChild(handToolEl);
        wrapper.appendChild(shapeToolEl);
        return wrapper;
    }

    function resetMap(controlDiv) {
        const controlUI = document.createElement("div");
        controlUI.style.backgroundColor = "#fff";
        controlUI.style.border = "2px solid #fff";
        controlUI.style.borderRadius = "3px";
        controlUI.style.boxShadow = "0 2px 6px rgba(0,0,0,.3)";
        controlUI.style.cursor = "pointer";
        controlUI.style.marginTop = "8px";
        controlUI.style.marginBottom = "22px";
        controlUI.style.textAlign = "center";
        controlUI.title = "Reset map";
        controlDiv.appendChild(controlUI);
        const controlText = document.createElement("div");
        controlText.style.color = "rgb(25,25,25)";
        controlText.style.fontFamily = "Roboto,Arial,sans-serif";
        controlText.style.fontSize = "10px";
        controlText.style.lineHeight = "16px";
        controlText.style.paddingLeft = "2px";
        controlText.style.paddingRight = "2px";
        controlText.innerHTML = "X";
        controlUI.appendChild(controlText);
        controlUI.addEventListener("click", () => {
            clearDrawing();
        });
    }

    function initialize() {
        let myLatlng = new google.maps.LatLng({{trim(explode(' ',$zone->center)[1], 'POINT()')}}, {{trim(explode(' ',$zone->center)[0], 'POINT()')}});
        const mapId = "{{ \App\Models\BusinessSetting::where('key', 'map_api_key')->first()->value }}"

        let myOptions = {
            zoom: 13,
            center: myLatlng,
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            mapId:mapId
        };
        map = new google.maps.Map(document.getElementById("map-canvas"), myOptions);
        bounds = new google.maps.LatLngBounds();

        const polygonCoords = [

            @foreach($area['coordinates'] as $coords)
             { lat: {{$coords[1]}}, lng: {{$coords[0]}} },
            @endforeach
        ];

        // Existing zone — shown read-only as a reference (blue).
        let existingZone = new google.maps.Polygon({
            map: map,
            paths: polygonCoords,
            editable: false,
            clickable: false,
            strokeColor: "#050df2",
            strokeOpacity: 0.8,
            strokeWeight: 2,
            fillColor: "#050df2",
            fillOpacity: 0.1,
        });

        existingZone.getPaths().forEach(function(path) {
            path.forEach(function(latlng) {
                bounds.extend(latlng);
                map.fitBounds(bounds);
            });
        });

        // Polyline (not Polygon) during drawing — Polygon would auto-render
        // the closing edge + fill once it has 3 vertices, pre-empting the
        // user's "click first dot to close" gesture.
        drawingPolyline = new google.maps.Polyline({
            map: map,
            editable: false,
            clickable: false,
            strokeColor: "#FF0000",
            strokeOpacity: 0.8,
            strokeWeight: 2,
        });
        drawingPolyline.setPath([]);
        const polylinePath = drawingPolyline.getPath();
        lastpolygon = drawingPolyline;

        google.maps.event.addListener(polylinePath, "set_at", updateCoordinates);
        google.maps.event.addListener(polylinePath, "insert_at", updateCoordinates);
        google.maps.event.addListener(polylinePath, "remove_at", updateCoordinates);

        google.maps.event.addListener(map, "click", function (event) {
            if (!drawingMode) return;
            if (polygonClosed) return;
            polylinePath.push(event.latLng);
            updateCoordinates();
        });

        map.controls[google.maps.ControlPosition.LEFT_TOP].push(buildDrawingControl());
        setDrawingMode(false);

        const resetDiv = document.createElement("div");
        resetMap(resetDiv);
        map.controls[google.maps.ControlPosition.RIGHT_TOP].push(resetDiv);

        const input = document.getElementById("pac-input");
            const searchBox = new google.maps.places.SearchBox(input);
            map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
            map.addListener("bounds_changed", () => {
                searchBox.setBounds(map.getBounds());
            });
            let markers = [];
            searchBox.addListener("places_changed", () => {
                const places = searchBox.getPlaces();

                if (places.length == 0) {
                return;
                }
                markers.forEach((marker) => {
                marker.map = null;
                });
                markers = [];
                const bounds = new google.maps.LatLngBounds();
                places.forEach((place) => {
                if (!place.geometry || !place.geometry.location) {
                    return;
                }
                const { AdvancedMarkerElement } = google.maps.marker;

                markers.push(
                    new AdvancedMarkerElement({
                    map,
                    title: place.name,
                    position: place.geometry.location,
                    })
                );

                if (place.geometry.viewport) {
                    bounds.union(place.geometry.viewport);
                } else {
                    bounds.extend(place.geometry.location);
                }
                });
                map.fitBounds(bounds);
            });

        set_all_zones();
    }

    function set_all_zones()
    {
        $.get({
            url: '{{route('admin.zone.zoneCoordinates')}}/{{$zone->id}}',
            dataType: 'json',
            success: function (data) {
                for(let i=0; i<data.length;i++)
                {
                    polygons.push(new google.maps.Polygon({
                        paths: data[i],
                        strokeColor: "#FF0000",
                        strokeOpacity: 0.8,
                        strokeWeight: 2,
                        fillColor: "#FF0000",
                        fillOpacity: 0.1,
                        clickable: false,
                    }));
                    polygons[i].setMap(map);
                }

            },
        });
    }

    $(document).on('ready', function(){
        $("#zone_form").on('keydown', function(e){
            if (e.keyCode === 13) {
                e.preventDefault();
            }
        });

        $("#zone_form").on('submit', function (e) {
            const startedDrawing = drawingPolyline && drawingPolyline.getPath().getLength() > 0;
            if (startedDrawing && !polygonClosed) {
                e.preventDefault();
                toastr.warning("{{ translate('Connect_the_last_dot_to_the_first_dot_to_close_the_polygon_before_saving') }}");
                return false;
            }
        });
    });

    $('#reset_btn').click(function(){
        location.reload(true);
    })

</script>
@endpush
