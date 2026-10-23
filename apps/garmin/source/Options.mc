import Toybox.Lang;
import Toybox.Timer;
import Toybox.WatchUi;

// The route list's options, as on Garmin's own pages: the three dots in the top right corner, or the menu button.
// Refresh fetches the list again, for a route saved in the planner since the app opened. Unlink asks first.
module Options {
    var _timer = null;

    function show() {
        var menu = new WatchUi.Menu2({ :title => "Options" });

        menu.addItem(new WatchUi.MenuItem("Refresh routes", "Get new saved routes", :refresh, null));
        menu.addItem(new WatchUi.MenuItem("Unlink device", "Sign out on this device", :unlink, null));
        WatchUi.pushView(menu, new OptionsDelegate(), WatchUi.SLIDE_UP);
    }

    // Loads the list again in place of the one under the options.
    function refresh() {
        Routes.open(WatchUi.SLIDE_IMMEDIATE);
    }

    // The confirmation closes itself once it has its answer, so the pairing screen takes over the list just after.
    function unlink() {
        Api.unlink();
        _timer = new Timer.Timer();
        _timer.start(new Lang.Method(Options, :pair), 50, false);
    }

    function pair() as Void {
        _timer = null;
        WatchUi.switchToView(new PairView(), new WatchUi.BehaviorDelegate(), WatchUi.SLIDE_RIGHT);
    }
}

class OptionsDelegate extends WatchUi.Menu2InputDelegate {
    function initialize() {
        Menu2InputDelegate.initialize();
    }

    // The options close first, so what follows takes the list's place.
    function onSelect(item) {
        WatchUi.popView(WatchUi.SLIDE_IMMEDIATE);

        if (item.getId() == :refresh) {
            Options.refresh();
            return;
        }

        WatchUi.pushView(new WatchUi.Confirmation("Unlink this device?"), new UnlinkDelegate(), WatchUi.SLIDE_IMMEDIATE);
    }
}

// Asks before unlinking, since linking again needs a new code from the site.
class UnlinkDelegate extends WatchUi.ConfirmationDelegate {
    function initialize() {
        ConfirmationDelegate.initialize();
    }

    function onResponse(response) {
        if (response == WatchUi.CONFIRM_YES) {
            Options.unlink();
        }

        return true;
    }
}
