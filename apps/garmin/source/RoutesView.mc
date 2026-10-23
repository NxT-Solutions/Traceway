import Toybox.Graphics;
import Toybox.Lang;
import Toybox.Math;
import Toybox.System;
import Toybox.WatchUi;

// Fetches the route list, then hands over to a menu. A multisport plan shows one entry per land leg.
module Routes {
    // The route list, loaded afresh.
    function open(transition) {
        var view = new RoutesView();
        WatchUi.switchToView(view, new RoutesDelegate(view), transition);
    }
}

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
            Pairing.open(WatchUi.SLIDE_IMMEDIATE);
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

                var entry = { :name => route["name"], :title => route["title"], :sport => route["sport"], :summary => numbers, :detail => numbers, :brief => distance(route), :path => base + "/fit", :line => base + "/line", :bounds => route["bounds"] };

                menu.addItem(item(entry, entries.size(), known, Rows.fit(route["title"], Graphics.FONT_MEDIUM)));
                entries.add(entry);
            }

            for (var leg = 0; leg < legs.size(); leg++) {
                var part = legs[leg];
                var which = "Leg " + part["n"] + " of " + route["title"];

                var entry = { :name => part["name"], :title => part["name"], :sport => part["sport"], :summary => "Leg " + part["n"] + ", " + summary(part), :detail => which, :brief => "Leg " + part["n"], :path => base + "/fit/" + part["n"], :line => base + "/line/" + part["n"], :bounds => part["bounds"] };

                menu.addItem(item(entry, entries.size(), known, Rows.fit(part["name"], Graphics.FONT_MEDIUM)));
                entries.add(entry);
            }
        }

        if (entries.size() == 0) {
            show("No saved routes yet", "Save a route in the Traceway planner, then select to look again.");
            return;
        }

        Rows.options(menu);
        WatchUi.switchToView(menu, new RoutesMenuDelegate(entries), WatchUi.SLIDE_IMMEDIATE);
    }

    // One route's menu item. A route in the device's Courses has a green tile with a check, and says Saved.
    function item(entry, id, known, label) {
        var saved = Courses.find(known, entry) != null;
        var row = Rows.route(id, label, Rows.detail(entry, saved), entry[:sport], saved);

        entry[:item] = row;

        return row;
    }

    function summary(route) {
        var text = distance(route);

        if (route["ascent_metres"] > 0) {
            text += ", +" + route["ascent_metres"] + " m";
        }

        return text;
    }

    // A round watch draws the highlighted route larger and cuts what does not fit, so from 10 km it drops the decimal.
    function distance(route) {
        var km = route["distance_metres"] / 1000.0;
        var round = System.getDeviceSettings().screenShape == System.SCREEN_SHAPE_ROUND;

        return (round && km >= 9.95 ? Math.round(km).toNumber().toString() : km.format("%.1f")) + " km";
    }

    function show(title, detail) {
        _title = title;
        _detail = detail;
        WatchUi.requestUpdate();
    }

    function onUpdate(dc) {
        Brand.message(dc, _title, _detail);

        if (_detail != null && ownDots()) {
            Brand.optionsButton(dc, Brand.optionsBox(dc.getWidth(), Brand.optionsBand(dc.getWidth())), Brand.PAPER);
        }
    }

    // From API 6 a bike computer puts Garmin's own three dots in its title bar for a screen with options, so only an
    // older Edge needs them drawn. A round watch opens options with its menu button.
    function ownDots() {
        return Brand.hasCorners() && System.getDeviceSettings().monkeyVersion[0] < 6;
    }

    // While the list loads, its answer replaces whatever is on top, so nothing else may open over this screen until then.
    function loading() {
        return _detail == null;
    }

    // Asks again after a message, for a route saved in the planner or a phone back in reach.
    function retry() {
        if (loading()) {
            return;
        }

        show("Loading routes", null);
        Api.get("/api/device/routes", method(:onRoutes));
    }
}

// On a message screen: select looks again, and the menu button, or the three dots on a touch screen, opens the options.
// While the list loads, both wait.
class RoutesDelegate extends WatchUi.BehaviorDelegate {
    var _view;

    function initialize(view) {
        BehaviorDelegate.initialize();
        _view = view;
    }

    function onMenu() {
        if (!_view.loading()) {
            Options.show();
        }

        return true;
    }

    function onKey(keyEvent) {
        var key = keyEvent.getKey();

        if (key == WatchUi.KEY_ENTER || key == WatchUi.KEY_START) {
            _view.retry();
            return true;
        }

        return false;
    }

    // A touch screen sends a tap as select first. Declining it hands the tap, with its point, to onTap.
    function onSelect() {
        return false;
    }

    // A tap near the three dots opens the options. Anywhere else looks again.
    function onTap(clickEvent) {
        if (_view.loading()) {
            return true;
        }

        var point = clickEvent.getCoordinates();
        var width = System.getDeviceSettings().screenWidth;
        var band = Brand.optionsBand(width);
        var box = Brand.optionsBox(width, band);

        if (_view.ownDots() && point[0] >= box[0] - box[2] && point[1] <= band) {
            Options.show();
        } else {
            _view.retry();
        }

        return true;
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
            Options.show();
            return;
        }

        Detail.show(_entries[id]);
    }

    // A tap on the title, where the three dots are.
    function onTitle() {
        Options.show();
    }

    function onMenu() {
        Options.show();
        return true;
    }
}

// Repaints a route's row after it is saved or removed, so the list is right on the way back.
function markSaved(entry, saved) {
    var item = entry[:item];

    if (item != null) {
        Rows.mark(item, entry[:sport], saved, Rows.detail(entry, saved));
    }
}
