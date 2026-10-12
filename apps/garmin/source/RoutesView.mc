import Toybox.Lang;
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
        var menu = new WatchUi.Menu2({ :title => new WordmarkTitle() });
        var routes = data["routes"];

        for (var index = 0; index < routes.size(); index++) {
            var route = routes[index];
            var path = "/api/device/routes/" + route["id"] + "/fit";
            var legs = route["legs"];

            if (legs.size() == 0) {
                menu.addItem(new WatchUi.IconMenuItem(route["title"], summary(route), entries.size(), new WatchUi.Bitmap({ :rezId => Brand.sportIcon(route["sport"]) }), null));
                entries.add({ :name => route["name"], :title => route["title"], :sport => route["sport"], :path => path });
            }

            for (var leg = 0; leg < legs.size(); leg++) {
                var part = legs[leg];

                menu.addItem(new WatchUi.IconMenuItem(part["name"], "Leg " + part["n"] + " of " + route["title"], entries.size(), new WatchUi.Bitmap({ :rezId => Brand.sportIcon(part["sport"]) }), null));
                entries.add({ :name => part["name"], :title => part["name"], :sport => part["sport"], :path => path + "/" + part["n"] });
            }
        }

        if (entries.size() == 0) {
            show("No saved routes yet", "Save a route in the Traceway planner, then open this again.");
            return;
        }

        menu.addItem(new WatchUi.MenuItem("Unlink device", null, -1, null));
        WatchUi.switchToView(menu, new RoutesMenuDelegate(entries), WatchUi.SLIDE_IMMEDIATE);
    }

    function summary(route) {
        var text = (route["distance_metres"] / 1000.0).format("%.1f") + " km";

        if (route["ascent_metres"] > 0) {
            text += ", " + route["ascent_metres"] + " m up";
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

        var view = new DownloadView(_entries[id]);
        WatchUi.pushView(view, new DownloadDelegate(view), WatchUi.SLIDE_LEFT);
    }
}
