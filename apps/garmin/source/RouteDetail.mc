import Toybox.Graphics;
import Toybox.Lang;
import Toybox.Math;
import Toybox.PersistedContent;
import Toybox.System;
import Toybox.WatchUi;

// One route before it goes on the device: its shape, its numbers, and two actions.
// The primary action starts the course in the device's own navigation. Save only puts it in Courses,
// and once it is there, Remove takes it out again.
// Both work by touch and by buttons: up and down move the focus, enter or start presses it.
class RouteDetail {
    const START = 0;
    const SAVE = 1;
    const REMOVE = 2;

    var entry;
    var line = null;
    var course = null;
    var state = :ready;
    var code = 0;
    var focus = 0;
    var buttons = [];
    var view;
    var _launch = false;

    function initialize(item, screen) {
        entry = item;
        view = screen;
        course = Courses.find(Courses.index(), entry);
        state = course == null ? :ready : :stored;
    }

    function load() {
        if (line == null) {
            Api.get(entry[:line], method(:onLine));
        }
    }

    function onLine(responseCode, data) {
        if (responseCode == 401) {
            Api.forget();
            WatchUi.switchToView(new PairView(), new WatchUi.BehaviorDelegate(), WatchUi.SLIDE_IMMEDIATE);
            return;
        }

        if (responseCode != 200 || !(data instanceof Dictionary) || !(data["line"] instanceof Array) || data["line"].size() < 2) {
            line = [];
            WatchUi.requestUpdate();
            return;
        }

        var points = [];
        var raw = data["line"];

        for (var index = 0; index < raw.size(); index++) {
            points.add([raw[index][0].toFloat(), raw[index][1].toFloat()]);
        }

        line = points;
        view.onLine(points);
        WatchUi.requestUpdate();
    }

    // The buttons on screen, in focus order.
    function actions() {
        if (state == :working || state == :gone) {
            return [];
        }

        if (state == :foreign) {
            return [START];
        }

        return course != null ? [START, REMOVE] : [START, SAVE];
    }

    function press(action) {
        if (state == :working || state == :gone) {
            return;
        }

        if (action == START && course != null) {
            System.exitTo(course.toIntent());
            return;
        }

        if (action == REMOVE) {
            WatchUi.pushView(new WatchUi.Confirmation("Remove from Courses?"), new RemoveDelegate(self), WatchUi.SLIDE_IMMEDIATE);
            return;
        }

        _launch = action == START;
        state = :working;
        WatchUi.requestUpdate();
        Api.course(entry[:path], method(:onCourse));
    }

    function pressFocused() {
        var list = actions();

        if (list.size() > 0) {
            press(list[focus < list.size() ? focus : 0]);
        }
    }

    function move(step) {
        var count = actions().size();

        if (count > 1) {
            focus = (focus + step + count) % count;
            WatchUi.requestUpdate();
        }
    }

    function onCourse(responseCode, data) {
        code = responseCode;

        if (responseCode == 401) {
            Api.forget();
            WatchUi.switchToView(new PairView(), new WatchUi.BehaviorDelegate(), WatchUi.SLIDE_IMMEDIATE);
            return;
        }

        if (responseCode != 200) {
            state = responseCode == 404 ? :gone : :failed;
            WatchUi.requestUpdate();
            return;
        }

        course = stored(data);
        state = :saved;
        focus = 0;

        if (course != null) {
            Courses.remember(entry, course);
            markSaved(entry, true);
        }

        if (_launch && course != null) {
            System.exitTo(course.toIntent());
            return;
        }

        WatchUi.requestUpdate();
    }

    // The new course, from the download itself or else by name among the stored courses.
    function stored(data) {
        if (data instanceof PersistedContent.Iterator) {
            var first = data.next();

            if (first instanceof PersistedContent.Course) {
                return first;
            }
        }

        return Courses.index()[:names][entry[:name]];
    }

    // Takes the course out of the device's Courses. A course that came from Garmin Connect or a cable belongs to the
    // device, not to this app, so the device refuses, and the rider is told where to remove it.
    function remove() {
        if (course == null) {
            return;
        }

        try {
            course.remove();
        } catch (error) {
            state = :foreign;
            focus = 0;
            WatchUi.requestUpdate();
            return;
        }

        Courses.forget(entry);
        markSaved(entry, false);
        course = null;
        state = :removed;
        focus = 0;
        WatchUi.requestUpdate();
    }

    function status() {
        if (state == :working) {
            return _launch ? "Loading the course" : "Saving to Courses";
        }

        if (state == :saved) {
            return "Saved to Courses";
        }

        if (state == :stored) {
            return "Saved in Courses";
        }

        if (state == :removed) {
            return "Removed from Courses";
        }

        if (state == :foreign) {
            return "Saved outside Traceway. Remove it under Courses.";
        }

        if (state == :gone) {
            return "No longer in Traceway";
        }

        if (state == :failed) {
            return code < 0 ? "No phone connection" : "Could not load. Try again.";
        }

        return entry[:summary];
    }

    // The panel under the route: name, numbers or status, then the buttons.
    // A round screen narrows towards the bottom, so there the text keeps to one line and each button fits the width of the circle where it sits.
    function drawPanel(dc, top) {
        var width = dc.getWidth();
        var height = dc.getHeight();
        var round = System.getDeviceSettings().screenShape == System.SCREEN_SHAPE_ROUND;
        var share = round ? 0.7 : 0.88;
        var gap = (height * 0.025).toNumber();
        var buttonFont = round ? Graphics.FONT_TINY : Graphics.FONT_SMALL;

        dc.setColor(Brand.INK, Brand.INK);
        dc.fillRectangle(0, top, width, height - top);
        dc.setColor(Brand.marker(), Brand.marker());
        dc.fillRectangle(0, top, width, 2);

        var title = Brand.fit(dc, Graphics.FONT_SMALL, entry[:title], share, 1);
        var detail = Brand.fit(dc, Graphics.FONT_XTINY, status(), share, round ? 1 : 2);
        var list = actions();
        // Room for the label, plus a focus ring and its gap inside every button, so both buttons are always one size.
        // A round screen is narrow where the buttons sit, so there the buttons are a little shorter and wider.
        var tall = dc.getFontHeight(buttonFont) + (round ? 10 : 16);
        var total = Brand.heightOf(dc, Graphics.FONT_SMALL, title) + Brand.heightOf(dc, Graphics.FONT_XTINY, detail);

        if (list.size() > 0) {
            total += gap * 2 + tall;
        }

        var bottom = round ? (height * 0.93).toNumber() : height;
        var spare = bottom - top - gap - total;
        // A round screen narrows towards the bottom, so there the group starts right under the route, where the buttons
        // get the most width. A rectangle centres it in the panel.
        var y = top + gap + (spare > 0 && !round ? spare / 2 : 0);

        y += Brand.text(dc, y, Graphics.FONT_SMALL, Brand.PAPER, title);
        y += Brand.text(dc, y, Graphics.FONT_XTINY, Brand.MUTED, detail);

        buttons = [];

        if (list.size() > 0) {
            var space = (width * share).toNumber();

            if (round) {
                space = chord(width, y + gap * 2 + tall);
            }

            drawButtons(dc, y + gap * 2, tall, list, space, buttonFont);
        }
    }

    // The width of a round screen at a height, less a margin.
    function chord(width, y) {
        var radius = width / 2.0;
        var offset = y - radius;
        var half = radius * radius - offset * offset;

        return half > 0 ? (Math.sqrt(half) * 2 * 0.94).toNumber() : (width * 0.5).toNumber();
    }

    function drawButtons(dc, y, tall, list, space, font) {
        var width = dc.getWidth();
        var round = System.getDeviceSettings().screenShape == System.SCREEN_SHAPE_ROUND;
        var count = list.size();
        var gap = count > 1 ? (width * (round ? 0.03 : 0.04)).toNumber() : 0;
        var room = count > 1 ? space - gap : (space * 0.62).toNumber();
        var label = Brand.verb(entry[:sport]);
        var fonts = [Graphics.FONT_SMALL, Graphics.FONT_TINY, Graphics.FONT_XTINY];
        var step = font == Graphics.FONT_SMALL ? 0 : 1;

        // Both labels share one size, the largest that keeps 30% of the button's height clear of each end of every
        // button. At the label's height the rounded ends curve in only a few pixels, so that clears them with room to spare.
        while (step < fonts.size() - 1 && labels(dc, list, label, fonts[step]) + count * tall * 6 / 10 > room) {
            step++;
        }

        font = fonts[step];

        // The width is shared out so every label has the same space on each side: a longer label gets a wider button.
        var pad = (room - labels(dc, list, label, font)) / (count * 2);
        var x = (width - room - gap * (count - 1)) / 2;

        // Every button fills its whole box. The focused one shows a paper ring at the box's edge, a gap, and the button
        // inside it; the others fill the whole box. So the buttons are always the same height.
        var left = x;

        for (var index = 0; index < count; index++) {
            var action = list[index];
            var text = name(action, label);
            var each = dc.getTextWidthInPixels(text, font) + pad * 2;
            var inset = count > 1 && index == focus ? (round ? 3 : 4) : 0;

            if (inset > 0) {
                outline(dc, left, y, each, tall, 0);
            }

            if (action == START) {
                dc.setColor(Brand.marker(), Graphics.COLOR_TRANSPARENT);
                dc.fillRoundedRectangle(left + inset, y + inset, each - inset * 2, tall - inset * 2, tall / 2 - inset);
                dc.setColor(Brand.INK, Graphics.COLOR_TRANSPARENT);
            } else {
                outline(dc, left, y, each, tall, inset);
            }

            dc.drawText(left + each / 2, labelTop(y, tall, font), font, text, Graphics.TEXT_JUSTIFY_CENTER);

            buttons.add([action, left, y, each, tall]);
            left += each + gap;
        }
    }

    // Where a label starts so its letters sit in the middle of a button this tall. The labels have no letters below
    // the line, so the label is centred on the font's ascent, not its whole height. A font whose ascent is more than
    // three quarters of its height leaves room above its capitals, which drops the letters by about 8% of the font
    // height, so there the label is lifted by that. Measured in the simulator on every device in the manifest; see
    // the README for how to measure again.
    function labelTop(y, tall, font) {
        var ascent = Graphics.getFontAscent(font);
        var height = Graphics.getFontHeight(font);
        var lift = 0;

        if (ascent * 4 > height * 3) {
            lift = (height * 8 + 50) / 100;
            lift = lift < 2 ? 2 : lift;
        }

        return y + (tall - ascent) / 2 - lift;
    }

    // A two-pixel paper outline of a button's box, drawn this far inside it.
    function outline(dc, left, y, each, tall, inset) {
        dc.setColor(Brand.PAPER, Graphics.COLOR_TRANSPARENT);
        dc.setPenWidth(2);
        dc.drawRoundedRectangle(left + inset + 1, y + inset + 1, each - inset * 2 - 2, tall - inset * 2 - 2, tall / 2 - inset);
        dc.setPenWidth(1);
    }

    function name(action, label) {
        if (action == START) {
            return label;
        }

        return action == REMOVE ? "Remove" : "Save";
    }

    // The width of all the labels together.
    function labels(dc, list, label, font) {
        var sum = 0;

        for (var index = 0; index < list.size(); index++) {
            sum += dc.getTextWidthInPixels(name(list[index], label), font);
        }

        return sum;
    }

    // The action under a tap, or null.
    function hit(point) {
        for (var index = 0; index < buttons.size(); index++) {
            var button = buttons[index];

            if (point[0] >= button[1] && point[0] <= button[1] + button[3] && point[1] >= button[2] - 6 && point[1] <= button[2] + button[4] + 6) {
                return button[0];
            }
        }

        return null;
    }

    // Where the route area ends and the panel starts. A round screen gives the panel more room, since its bottom is narrow.
    function panelTop(dc) {
        return (dc.getHeight() * Detail.mapShare()).toNumber();
    }
}

class RouteDetailDelegate extends WatchUi.BehaviorDelegate {
    var _detail;

    function initialize(detail) {
        BehaviorDelegate.initialize();
        _detail = detail;
    }

    function onKey(keyEvent) {
        var key = keyEvent.getKey();

        if (key == WatchUi.KEY_ENTER || key == WatchUi.KEY_START) {
            _detail.pressFocused();
            return true;
        }

        return false;
    }

    // Back leaves a panned map first, then the route.
    function onBack() {
        return _detail.view.leaveBrowse();
    }

    function onNextPage() {
        _detail.move(1);
        return true;
    }

    function onPreviousPage() {
        _detail.move(-1);
        return true;
    }

    // A touch screen sends a tap as select first. Declining it hands the tap, with its point, to onTap.
    function onSelect() {
        return false;
    }

    function onTap(clickEvent) {
        var action = _detail.hit(clickEvent.getCoordinates());

        if (action == null) {
            return false;
        }

        _detail.press(action);

        return true;
    }
}

// Asks before a course leaves the device, since it cannot come back without the phone.
class RemoveDelegate extends WatchUi.ConfirmationDelegate {
    var _detail;

    function initialize(detail) {
        ConfirmationDelegate.initialize();
        _detail = detail;
    }

    function onResponse(response) {
        if (response == WatchUi.CONFIRM_YES) {
            _detail.remove();
        }

        return true;
    }
}
