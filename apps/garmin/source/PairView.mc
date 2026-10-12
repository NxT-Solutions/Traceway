import Toybox.Lang;
import Toybox.Timer;
import Toybox.WatchUi;

// Shows a six-character code. The rider types it in Settings, then Devices, and this view collects the token.
class PairView extends WatchUi.View {
    var _text = "Getting a code";
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
        show("Getting a code");
        Api.post("/api/device/pairings", { "provider" => "garmin" }, method(:onPairing));
    }

    function onPairing(code, data) {
        if (code != 201 || !(data instanceof Dictionary)) {
            show("No connection.\nCheck the phone.");
            return;
        }

        _secret = data["secret"];
        show("On " + Api.host() + "\nSettings, Devices\n\n" + data["code"]);

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

    function show(text) {
        _text = text;
        WatchUi.requestUpdate();
    }

    function onUpdate(dc) {
        Screen.message(dc, _text);
    }
}
