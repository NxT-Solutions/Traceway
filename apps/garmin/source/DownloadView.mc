import Toybox.Graphics;
import Toybox.Lang;
import Toybox.PersistedContent;
import Toybox.System;
import Toybox.WatchUi;

// Downloads one course. Once it is stored, the start key opens it in the device's own navigation.
class DownloadView extends WatchUi.View {
    var course = null;
    var button = null;
    var _entry;
    var _state = :loading;
    var _code = 0;
    var _sport;
    var _check;

    function initialize(entry) {
        View.initialize();
        _entry = entry;
        _sport = WatchUi.loadResource(Brand.sportIconLarge(entry[:sport]));
        _check = WatchUi.loadResource(Rez.Drawables.Check);
    }

    function onShow() {
        if (_state == :loading && course == null && _code == 0) {
            Api.course(_entry[:path], method(:onCourse));
        }
    }

    function onCourse(code, data) {
        _code = code;

        if (code != 200) {
            _state = :failed;
        } else {
            course = stored(data);
            _state = :saved;
        }

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
        Brand.ground(dc);

        var width = dc.getWidth();
        var height = dc.getHeight();
        var icon = _state == :saved ? _check : _sport;
        var gap = (height * 0.04).toNumber();
        var y = (height * 0.14).toNumber();

        dc.drawBitmap((width - icon.getWidth()) / 2, y, icon);
        y += icon.getHeight() + gap;
        y += Brand.text(dc, y, Graphics.FONT_MEDIUM, Brand.PAPER, _entry[:title], 0.8, 2);

        if (_state == :loading) {
            Brand.text(dc, y, Graphics.FONT_XTINY, Brand.MUTED, "Loading onto the device", 0.74, 1);
            return;
        }

        if (_state == :failed) {
            Brand.text(dc, y, Graphics.FONT_XTINY, Brand.MUTED, "The course did not load (" + _code + "). Go back and try again.", 0.74, 3);
            return;
        }

        y += Brand.text(dc, y, Graphics.FONT_XTINY, Brand.MUTED, "Saved to Courses", 0.74, 1);

        if (course != null) {
            drawRide(dc, width, y + gap * 2);
        }
    }

    // A marker-green button. The start key or a tap opens the course.
    function drawRide(dc, width, y) {
        var font = Graphics.FONT_SMALL;
        var label = "Ride it";
        var buttonWidth = dc.getTextWidthInPixels(label, font) + (width * 0.16).toNumber();
        var buttonHeight = dc.getFontHeight(font) + 10;
        var x = (width - buttonWidth) / 2;

        button = [x, y, buttonWidth, buttonHeight];

        dc.setColor(Brand.MARKER, Graphics.COLOR_TRANSPARENT);
        dc.fillRoundedRectangle(x, y, buttonWidth, buttonHeight, buttonHeight / 2);
        dc.setColor(Brand.INK, Graphics.COLOR_TRANSPARENT);
        dc.drawText(width / 2, y + 5, font, label, Graphics.TEXT_JUSTIFY_CENTER);
    }
}

class DownloadDelegate extends WatchUi.BehaviorDelegate {
    var _view;

    function initialize(view) {
        BehaviorDelegate.initialize();
        _view = view;
    }

    // The start or enter key rides. A tap only rides on the button, so a stray touch does not start a course.
    function onKey(keyEvent) {
        var key = keyEvent.getKey();

        if (key == WatchUi.KEY_START || key == WatchUi.KEY_ENTER) {
            return ride();
        }

        return false;
    }

    // A touch screen sends a tap as select first. Declining it hands the tap, with its point, to onTap.
    function onSelect() {
        return false;
    }

    function onTap(clickEvent) {
        var button = _view.button;
        var point = clickEvent.getCoordinates();

        if (button != null && point[0] >= button[0] && point[0] <= button[0] + button[2] && point[1] >= button[1] && point[1] <= button[1] + button[3]) {
            return ride();
        }

        return false;
    }

    function ride() {
        if (_view.course == null) {
            return false;
        }

        System.exitTo(_view.course.toIntent());

        return true;
    }
}
