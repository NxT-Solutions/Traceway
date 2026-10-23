import Toybox.Graphics;
import Toybox.Lang;
import Toybox.Timer;
import Toybox.WatchUi;

// Shows a six-character code. The rider types it in Settings, then Devices, and this view collects the token.
module Pairing {
    function open(transition) {
        var view = new PairView();
        WatchUi.switchToView(view, new PairDelegate(view), transition);
    }

    // A bike computer from API 6 puts Garmin's three dots in the title bar of every app screen, this one too, so they
    // open options here as well: a new code, for one that is hard to read or was typed wrong.
    function options(view) {
        var menu = new WatchUi.Menu2({ :title => "Options" });

        menu.addItem(new WatchUi.MenuItem("New code", "Get another code", :code, null));
        WatchUi.pushView(menu, new PairOptionsDelegate(view), WatchUi.SLIDE_UP);
    }
}

// The menu button, or Garmin's dots, open the options. After No connection, select or a tap tries again.
class PairDelegate extends WatchUi.BehaviorDelegate {
    var _view;

    function initialize(view) {
        BehaviorDelegate.initialize();
        _view = view;
    }

    function onMenu() {
        Pairing.options(_view);
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

    // A touch screen sends a tap as select first. Declining it hands the tap to onTap.
    function onSelect() {
        return false;
    }

    function onTap(clickEvent) {
        _view.retry();
        return true;
    }
}

class PairOptionsDelegate extends WatchUi.Menu2InputDelegate {
    var _view;

    function initialize(view) {
        Menu2InputDelegate.initialize();
        _view = view;
    }

    function onSelect(item) {
        WatchUi.popView(WatchUi.SLIDE_IMMEDIATE);
        _view.open();
    }
}

class PairView extends WatchUi.View {
    var _code = null;
    var _problem = null;
    var _secret = null;
    var _timer = null;
    var _started = false;
    var _shown = false;

    function initialize() {
        View.initialize();
    }

    // The code stays the same behind the options, and the device asks for its token again once it is back in view.
    function onShow() {
        _shown = true;

        if (!_started) {
            _started = true;
            open();
        } else {
            wait();
        }
    }

    function onHide() {
        _shown = false;
        stop();
    }

    function open() {
        stop();
        _code = null;
        _problem = null;
        _secret = null;
        WatchUi.requestUpdate();
        Api.post("/api/device/pairings", { "provider" => "garmin" }, method(:onPairing));
    }

    // After No connection, asks for a code again.
    function retry() {
        if (_problem != null) {
            open();
        }
    }

    function onPairing(code, data) {
        if (code != 201 || !(data instanceof Dictionary)) {
            _problem = "No connection";
            WatchUi.requestUpdate();
            return;
        }

        _secret = data["secret"];
        _code = data["code"];
        WatchUi.requestUpdate();
        wait();
    }

    // Asks every few seconds whether the rider has typed the code, while this screen is in view.
    function wait() {
        stop();

        if (_shown && _secret != null) {
            _timer = new Timer.Timer();
            _timer.start(method(:poll), 5000, true);
        }
    }

    function poll() as Void {
        Api.post("/api/device/pairings/claim", { "secret" => _secret }, method(:onClaim));
    }

    function onClaim(code, data) {
        if (code == 200 && data instanceof Dictionary) {
            stop();
            Api.remember(data["token"]);
            Routes.open(WatchUi.SLIDE_LEFT);
        } else if (code == 410) {
            open();
        }
    }

    function stop() {
        if (_timer != null) {
            _timer.stop();
            _timer = null;
        }
    }

    function onUpdate(dc) {
        if (_problem != null) {
            Brand.message(dc, _problem, "Open Garmin Connect on the phone, then select to try again.");
            return;
        }

        if (_code == null) {
            Brand.message(dc, "Getting a code", null);
            return;
        }

        Brand.ground(dc);

        var width = dc.getWidth();
        var height = dc.getHeight();
        var small = Graphics.FONT_XTINY;
        var logo = WatchUi.loadResource(Rez.Drawables.Wordmark);
        var label = Brand.fit(dc, small, "Link this device", 0.8, 1);
        var where = Brand.fit(dc, small, "Enter it on " + Api.host(), 0.84, 1);
        var how = Brand.fit(dc, small, "Settings, then Devices", 0.84, 1);
        var codeHeight = dc.getFontHeight(Graphics.FONT_LARGE) + 5;
        var wide = (height * 0.07).toNumber();
        var narrow = (height * 0.03).toNumber();
        var total = logo.getHeight() + wide + Brand.heightOf(dc, small, label) + narrow + codeHeight + wide + Brand.heightOf(dc, small, where) + Brand.heightOf(dc, small, how);
        var y = Brand.top(dc, total);

        y += Brand.bitmap(dc, y, logo) + wide;
        y += Brand.text(dc, y, small, Brand.MUTED, label) + narrow;
        drawCode(dc, y, width);
        y += codeHeight + wide;
        y += Brand.text(dc, y, small, Brand.PAPER, where);
        Brand.text(dc, y, small, Brand.MUTED, how);
    }

    // The code as six slots, each underlined with the marker green.
    function drawCode(dc, y, width) {
        var font = Graphics.FONT_LARGE;
        var slot = dc.getTextWidthInPixels("W", font) + 4;
        var widest = (width * 0.8 / 7).toNumber();

        if (slot > widest) {
            slot = widest;
        }

        var gap = (slot * 0.2).toNumber();
        var group = slot * 3 + gap * 2;
        var middle = (slot * 0.6).toNumber();
        var left = (width - group * 2 - middle) / 2;
        var line = y + dc.getFontHeight(font) + 2;

        for (var index = 0; index < _code.length(); index++) {
            var x = left + index * (slot + gap) + (index >= 3 ? middle - gap : 0);

            dc.setColor(Brand.PAPER, Graphics.COLOR_TRANSPARENT);
            dc.drawText(x + slot / 2, y, font, _code.substring(index, index + 1), Graphics.TEXT_JUSTIFY_CENTER);
            dc.setColor(Brand.marker(), Graphics.COLOR_TRANSPARENT);
            dc.fillRoundedRectangle(x, line, slot, 3, 1);
        }
    }
}
