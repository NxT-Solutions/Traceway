import Toybox.Graphics;
import Toybox.Lang;
import Toybox.Math;
import Toybox.System;
import Toybox.WatchUi;

// Fetches the route list, then hands over to a menu. A multisport plan shows one entry per land leg.
class RoutesView extends WatchUi.View {
    var _title = "Loading routes";
    var _detail = null;
    var _asked = false;

    function initialize() {
        View.initialize();
    }

    function onShow() {
        if (!_asked) {
            _asked = true;
            Api.get("/api/device/routes", method(:onRoutes));
        }
    }

    function onRoutes(code, data) {
        if (code == 401) {
            Api.forget();
            WatchUi.switchToView(new PairView(), new WatchUi.BehaviorDelegate(), WatchUi.SLIDE_IMMEDIATE);
            return;
        }

        if (code != 200 || !(data instanceof Dictionary)) {
            show("No connection", "Open Garmin Connect on the phone.");
            return;
        }

        var entries = [];
        var menu = Rows.menu();
        var routes = data["routes"];
        var known = Courses.index();

        for (var index = 0; index < routes.size(); index++) {
            var route = routes[index];
            var base = "/api/device/routes/" + route["id"];
            var legs = route["legs"];

            if (legs.size() == 0) {
                var numbers = summary(route);

                var entry = { :name => route["name"], :title => route["title"], :sport => route["sport"], :summary => numbers, :path => base + "/fit", :line => base + "/line", :bounds => route["bounds"] };

                menu.addItem(item(entry, entries.size(), known, fit(route["title"], Graphics.FONT_MEDIUM), fit(numbers, Graphics.FONT_SMALL)));
                entries.add(entry);
            }

            for (var leg = 0; leg < legs.size(); leg++) {
                var part = legs[leg];
                var which = "Leg " + part["n"] + " of " + route["title"];

                var entry = { :name => part["name"], :title => part["name"], :sport => part["sport"], :summary => "Leg " + part["n"] + ", " + summary(part), :path => base + "/fit/" + part["n"], :line => base + "/line/" + part["n"], :bounds => part["bounds"] };

                menu.addItem(item(entry, entries.size(), known, fit(part["name"], Graphics.FONT_MEDIUM), fit(which, Graphics.FONT_SMALL)));
                entries.add(entry);
            }
        }

        if (entries.size() == 0) {
            show("No saved routes yet", "Save a route in the Traceway planner, then open this again.");
            return;
        }

        menu.addItem(Rows.plain(-1, "Unlink device"));
        WatchUi.switchToView(menu, new RoutesMenuDelegate(entries), WatchUi.SLIDE_IMMEDIATE);
    }

    // One route's menu item. Its tile is green when the route is in the device's Courses.
    function item(entry, id, known, label, detail) {
        var row = Rows.route(id, label, detail, entry[:sport], Courses.find(known, entry) != null);

        entry[:item] = row;

        return row;
    }

    // A menu does not shorten a label before its icon, so long names are cut here, with an ellipsis.
    function fit(text, font) {
        var width = (System.getDeviceSettings().screenWidth * 0.7).toNumber();

        return Graphics.fitTextToArea(text, font, width, Graphics.getFontHeight(font), true);
    }

    // A round watch draws the highlighted route larger and cuts what does not fit, so from 10 km it drops the decimal.
    function summary(route) {
        var km = route["distance_metres"] / 1000.0;
        var round = System.getDeviceSettings().screenShape == System.SCREEN_SHAPE_ROUND;
        var text = (round && km >= 9.95 ? Math.round(km).toNumber().toString() : km.format("%.1f")) + " km";

        if (route["ascent_metres"] > 0) {
            text += ", +" + route["ascent_metres"] + " m";
        }

        return text;
    }

    function show(title, detail) {
        _title = title;
        _detail = detail;
        WatchUi.requestUpdate();
    }

    function onUpdate(dc) {
        Brand.message(dc, _title, _detail);
    }
}

class RoutesMenuDelegate extends WatchUi.Menu2InputDelegate {
    var _entries;

    function initialize(entries) {
        Menu2InputDelegate.initialize();
        _entries = entries;
    }

    function onSelect(item) {
        var id = item.getId();

        if (id == -1) {
            Api.forget();
            WatchUi.switchToView(new PairView(), new WatchUi.BehaviorDelegate(), WatchUi.SLIDE_RIGHT);
            return;
        }

        Detail.show(_entries[id]);
    }
}

// Repaints a route's tile after it is saved or removed, so the list is right on the way back.
function markSaved(entry, saved) {
    var item = entry[:item];

    if (item != null) {
        Rows.mark(item, entry[:sport], saved);
    }
}
