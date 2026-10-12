import Toybox.Lang;
import Toybox.PersistedContent;
import Toybox.System;
import Toybox.WatchUi;

// Downloads one course. Once it is stored, the start key opens it in the device's own navigation.
class DownloadView extends WatchUi.View {
    var course = null;
    var _entry;
    var _text;
    var _asked = false;

    function initialize(entry) {
        View.initialize();
        _entry = entry;
        _text = "Loading\n" + entry[:name];
    }

    function onShow() {
        if (!_asked) {
            _asked = true;
            Api.course(_entry[:path], method(:onCourse));
        }
    }

    function onCourse(code, data) {
        if (code != 200) {
            _text = "Download failed\n(" + code + ")";
            WatchUi.requestUpdate();
            return;
        }

        course = stored(data);
        _text = course == null
            ? _entry[:name] + "\nis in Courses."
            : _entry[:name] + "\nis in Courses.\n\nStart to ride.";
        WatchUi.requestUpdate();
    }

    // The new course, from the download itself or else by name among the stored courses.
    function stored(data) {
        if (!(Toybox has :PersistedContent)) {
            return null;
        }

        if (data instanceof PersistedContent.Iterator) {
            var first = data.next();

            if (first instanceof PersistedContent.Course) {
                return first;
            }
        }

        var found = null;
        var courses = PersistedContent.getCourses();
        var item = courses.next();

        while (item != null) {
            if (item.getName().equals(_entry[:name])) {
                found = item;
            }

            item = courses.next();
        }

        return found;
    }

    function onUpdate(dc) {
        Screen.message(dc, _text);
    }
}

class DownloadDelegate extends WatchUi.BehaviorDelegate {
    var _view;

    function initialize(view) {
        BehaviorDelegate.initialize();
        _view = view;
    }

    function onSelect() {
        if (_view.course == null) {
            return false;
        }

        System.exitTo(_view.course.toIntent());

        return true;
    }
}
