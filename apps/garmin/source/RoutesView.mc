import Toybox.Lang;
import Toybox.WatchUi;

// Fetches the route list, then hands over to a menu. A multisport plan shows one entry per land leg.
class RoutesView extends WatchUi.View {
    var _text = "Loading routes";
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
            _text = "No connection.\nCheck the phone.";
            WatchUi.requestUpdate();
            return;
        }

        var entries = [];
        var menu = new WatchUi.Menu2({ :title => "Traceway" });
        var routes = data["routes"];

        for (var index = 0; index < routes.size(); index++) {
            var route = routes[index];
            var path = "/api/device/routes/" + route["id"] + "/fit";
            var legs = route["legs"];
            var km = (route["distance_metres"] / 1000.0).format("%.1f") + " km";

            if (legs.size() == 0) {
                menu.addItem(new WatchUi.MenuItem(route["title"], km, entries.size(), null));
                entries.add({ :name => route["name"], :path => path });
            }

            for (var leg = 0; leg < legs.size(); leg++) {
                menu.addItem(new WatchUi.MenuItem(legs[leg]["name"], "Leg " + legs[leg]["n"], entries.size(), null));
                entries.add({ :name => legs[leg]["name"], :path => path + "/" + legs[leg]["n"] });
            }
        }

        if (entries.size() == 0) {
            _text = "No saved routes.\nSave one in the planner.";
            WatchUi.requestUpdate();
            return;
        }

        menu.addItem(new WatchUi.MenuItem("Unlink device", null, -1, null));
        WatchUi.switchToView(menu, new RoutesMenuDelegate(entries), WatchUi.SLIDE_IMMEDIATE);
    }

    function onUpdate(dc) {
        Screen.message(dc, _text);
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
