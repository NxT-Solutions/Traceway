import Toybox.Graphics;
import Toybox.Lang;
import Toybox.Timer;
import Toybox.WatchUi;

// Shows a six-character code. The rider types it in Settings, then Devices, and this view collects the token.
class PairView extends WatchUi.View {
    var _code = null;
    var _problem = null;
    var _secret = null;
    var _timer = null;
    var _started = false;

    function initialize() {
        View.initialize();
    }

    function onShow() {
        if (!_started) {
            _started = true;
            open();
        }
    }

    function onHide() {
        stop();
        _started = false;
    }

    function open() {
        _code = null;
        _problem = null;
        WatchUi.requestUpdate();
        Api.post("/api/device/pairings", { "provider" => "garmin" }, method(:onPairing));
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

        stop();
        _timer = new Timer.Timer();
        _timer.start(method(:poll), 5000, true);
    }

    function poll() as Void {
        Api.post("/api/device/pairings/claim", { "secret" => _secret }, method(:onClaim));
    }

    function onClaim(code, data) {
        if (code == 200 && data instanceof Dictionary) {
            stop();
            Api.remember(data["token"]);
            WatchUi.switchToView(new RoutesView(), new WatchUi.BehaviorDelegate(), WatchUi.SLIDE_LEFT);
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
            Brand.message(dc, _problem, "Open Garmin Connect on the phone.");
            return;
        }

        if (_code == null) {
            Brand.message(dc, "Getting a code", null);
            return;
        }

        Brand.ground(dc);

        var width = dc.getWidth();
        var height = dc.getHeight();
        var y = Brand.wordmark(dc, (height * 0.14).toNumber());

        y += (height * 0.06).toNumber();
        Brand.text(dc, y, Graphics.FONT_XTINY, Brand.MUTED, "Link this device", 0.8, 1);
        y += dc.getFontHeight(Graphics.FONT_XTINY) + (height * 0.03).toNumber();

        drawCode(dc, y, width);
        y += dc.getFontHeight(Graphics.FONT_LARGE) + (height * 0.07).toNumber();

        Brand.text(dc, y, Graphics.FONT_XTINY, Brand.PAPER, "Enter it on " + Api.host(), 0.84, 1);
        y += dc.getFontHeight(Graphics.FONT_XTINY);
        Brand.text(dc, y, Graphics.FONT_XTINY, Brand.MUTED, "Settings, then Devices", 0.84, 1);
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
            dc.setColor(Brand.MARKER, Graphics.COLOR_TRANSPARENT);
            dc.fillRoundedRectangle(x, line, slot, 3, 1);
        }
    }
}
