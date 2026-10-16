import Toybox.Graphics;
import Toybox.Lang;
import Toybox.Math;
import Toybox.Position;
import Toybox.System;
import Toybox.WatchUi;

// The detail screen for a route. A device with maps shows the route on its own map. Others get the shape drawn on ink.
module Detail {
    (:maps)
    function show(entry) {
        var view = new RouteMapView(entry);
        WatchUi.pushView(view, new RouteDetailDelegate(view.detail), WatchUi.SLIDE_LEFT);
    }

    (:nomaps)
    function show(entry) {
        var view = new RouteSketchView(entry);
        WatchUi.pushView(view, new RouteDetailDelegate(view.detail), WatchUi.SLIDE_LEFT);
    }

    // An Edge 840, 850 or 1050 draws zoom and pan buttons across the top of its map, so the route is framed below them,
    // with a tight margin so the shorter area does not make the map zoom a step further out.
    (:mapcontrols)
    function controlsHeight() {
        return (System.getDeviceSettings().screenHeight * 0.12).toNumber();
    }

    (:mapcontrols)
    function margin() {
        return 0.02;
    }

    // Other devices draw nothing over the map, so the route gets the whole area above the panel.
    (:nomapcontrols)
    function controlsHeight() {
        return 0;
    }

    (:nomapcontrols)
    function margin() {
        return 0.06;
    }

    // How much of the screen height the route gets. The rest is the panel. A tall Edge, such as an 850, has room to
    // spare below, and the taller map keeps the route from being framed a zoom step further out.
    function mapShare() {
        var settings = System.getDeviceSettings();

        if (settings.screenShape == System.SCREEN_SHAPE_ROUND) {
            return 0.46;
        }

        return settings.screenHeight >= 600 ? 0.62 : 0.56;
    }

    // The south-west and north-east corners of a line of [lat, lon] points.
    function bounds(points) {
        var south = points[0][0];
        var north = south;
        var west = points[0][1];
        var east = west;

        for (var index = 1; index < points.size(); index++) {
            var point = points[index];

            south = point[0] < south ? point[0] : south;
            north = point[0] > north ? point[0] : north;
            west = point[1] < west ? point[1] : west;
            east = point[1] > east ? point[1] : east;
        }

        return [south, west, north, east];
    }
}

(:maps)
class RouteMapView extends WatchUi.MapView {
    var detail;

    // The map is framed on the route's box from the list, so it shows the right place before the line arrives.
    function initialize(entry) {
        MapView.initialize();
        detail = new RouteDetail(entry, self);
        setMapMode(WatchUi.MAP_MODE_PREVIEW);
        frame(entry[:bounds]);
    }

    function onShow() {
        detail.load();
    }

    // Draws the route in ink with a green start, framed with a margin above the panel.
    function onLine(points) {
        var polyline = new WatchUi.MapPolyline();

        polyline.setColor(Brand.INK);
        polyline.setWidth(4);

        for (var index = 0; index < points.size(); index++) {
            polyline.addLocation(location(points[index][0], points[index][1]));
        }

        setPolyline(polyline);

        var start = new WatchUi.MapMarker(location(points[0][0], points[0][1]));
        var dot = WatchUi.loadResource(Rez.Drawables.StartDot);
        start.setIcon(dot, dot.getWidth() / 2, dot.getHeight() / 2);
        setMapMarker(start);

        frame(Detail.bounds(points));
    }

    // Shows a south, west, north, east box with a margin, in the area above the panel.
    function frame(box) {
        var settings = System.getDeviceSettings();
        var south = box[0].toFloat();
        var west = box[1].toFloat();
        var north = box[2].toFloat();
        var east = box[3].toFloat();
        var padLat = (north - south) * Detail.margin() + 0.0003;
        var padLon = (east - west) * Detail.margin() + 0.0003;

        setScreenVisibleArea(0, Detail.controlsHeight(), settings.screenWidth, (settings.screenHeight * Detail.mapShare()).toNumber());
        setMapVisibleArea(location(north + padLat, west - padLon), location(south - padLat, east + padLon));
    }

    function leaveBrowse() {
        if (getMapMode() == WatchUi.MAP_MODE_BROWSE) {
            setMapMode(WatchUi.MAP_MODE_PREVIEW);
            WatchUi.requestUpdate();
            return true;
        }

        return false;
    }

    function location(latitude, longitude) {
        return new Position.Location({ :latitude => latitude, :longitude => longitude, :format => :degrees });
    }

    function onUpdate(dc) {
        MapView.onUpdate(dc);

        if (detail.line == null) {
            Brand.text(dc, (dc.getHeight() * 0.22).toNumber(), Graphics.FONT_XTINY, Brand.INK, "Loading the route");
        }

        detail.drawPanel(dc, detail.panelTop(dc));
    }
}

(:nomaps)
class RouteSketchView extends WatchUi.View {
    var detail;

    function initialize(entry) {
        View.initialize();
        detail = new RouteDetail(entry, self);
    }

    function onShow() {
        detail.load();
    }

    function onLine(points) {
    }

    function leaveBrowse() {
        return false;
    }

    function onUpdate(dc) {
        Brand.ground(dc);

        var top = detail.panelTop(dc);

        if (detail.line == null) {
            Brand.text(dc, top / 2, Graphics.FONT_XTINY, Brand.MUTED, "Loading the route");
        } else if (detail.line.size() > 1) {
            sketch(dc, detail.line, top);
        }

        detail.drawPanel(dc, top);
    }

    // The route fitted into the area above the panel, north up, with the start marked in green.
    function sketch(dc, points, bottom) {
        var width = dc.getWidth();
        var round = System.getDeviceSettings().screenShape == System.SCREEN_SHAPE_ROUND;
        var margin = round ? (width * 0.24).toNumber() : (width * 0.1).toNumber();
        var top = round ? (bottom * 0.3).toNumber() : (bottom * 0.12).toNumber();
        var box = Detail.bounds(points);
        var scaleX = Math.cos(Math.toRadians((box[0] + box[2]) / 2));
        var spanX = (box[3] - box[1]) * scaleX;
        var spanY = box[2] - box[0];
        var areaW = width - margin * 2;
        var areaH = bottom - top - (bottom * 0.08).toNumber();
        var scale = areaW / (spanX > 0.000001 ? spanX : 0.000001);

        if (spanY * scale > areaH) {
            scale = areaH / (spanY > 0.000001 ? spanY : 0.000001);
        }

        var offsetX = margin + (areaW - spanX * scale) / 2;
        var offsetY = top + (areaH - spanY * scale) / 2;
        var startX = offsetX + (points[0][1] - box[1]) * scaleX * scale;
        var startY = offsetY + (box[2] - points[0][0]) * scale;
        var lastX = startX;
        var lastY = startY;

        dc.setColor(Brand.PAPER, Graphics.COLOR_TRANSPARENT);
        dc.setPenWidth(3);

        for (var index = 1; index < points.size(); index++) {
            var x = offsetX + (points[index][1] - box[1]) * scaleX * scale;
            var y = offsetY + (box[2] - points[index][0]) * scale;

            dc.drawLine(lastX, lastY, x, y);
            lastX = x;
            lastY = y;
        }

        dc.setPenWidth(1);

        dc.setColor(Brand.INK, Graphics.COLOR_TRANSPARENT);
        dc.fillCircle(startX, startY, 7);
        dc.setColor(Brand.marker(), Graphics.COLOR_TRANSPARENT);
        dc.fillCircle(startX, startY, 5);
    }
}
