import Toybox.Application;
import Toybox.WatchUi;

// Lists the rider's saved Traceway routes and loads one onto the device as a course.
class TracewayApp extends Application.AppBase {
    function initialize() {
        AppBase.initialize();
    }

    function getInitialView() {
        if (Api.token() == null) {
            var pairView = new PairView();
            return [pairView, new PairDelegate(pairView)];
        }

        var view = new RoutesView();

        return [view, new RoutesDelegate(view)];
    }
}
