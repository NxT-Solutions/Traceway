import Toybox.Graphics;
import Toybox.Lang;
import Toybox.System;
import Toybox.WatchUi;

// The Traceway look on a device: ink ground, paper type, and the green marker used sparingly.
module Brand {
    const INK = 0x071F20;
    const PAPER = 0xF7F5EC;
    const MUTED = 0x9AA9A6;

    // The marker green. A 64-colour screen gets the nearest bright green in its palette.
    (:color)
    function marker() {
        return 0x97CB46;
    }

    (:mip)
    function marker() {
        return 0xAAFF55;
    }

    function ground(dc) {
        dc.setColor(PAPER, INK);
        dc.clear();
    }

    // A bitmap centred across the screen, with its top at y. Returns its height.
    function bitmap(dc, y, image) {
        dc.drawBitmap((dc.getWidth() - image.getWidth()) / 2, y, image);

        return image.getHeight();
    }

    // Wraps text to a share of the screen width and a number of lines, with an ellipsis past that.
    function fit(dc, font, value, widthShare, lines) {
        var lineHeight = dc.getFontHeight(font);

        return Graphics.fitTextToArea(value, font, (dc.getWidth() * widthShare).toNumber(), lineHeight * lines + lineHeight / 2, true);
    }

    function heightOf(dc, font, fitted) {
        return dc.getTextDimensions(fitted, font)[1];
    }

    // Fitted text centred across the screen, with its top at y. Returns its height.
    function text(dc, y, font, color, fitted) {
        dc.setColor(color, Graphics.COLOR_TRANSPARENT);
        dc.drawText(dc.getWidth() / 2, y, font, fitted, Graphics.TEXT_JUSTIFY_CENTER);

        return heightOf(dc, font, fitted);
    }

    // Where a group of this height starts, so it sits in the middle of the screen.
    function top(dc, total) {
        return (dc.getHeight() - total) / 2;
    }

    // A screen with the wordmark and one message under it.
    function message(dc, title, detail) {
        ground(dc);

        var height = dc.getHeight();
        var logo = WatchUi.loadResource(Rez.Drawables.Wordmark);
        var gap = (height * 0.07).toNumber();
        var titleText = fit(dc, Graphics.FONT_SMALL, title, 0.78, 2);
        var detailText = detail == null ? null : fit(dc, Graphics.FONT_XTINY, detail, 0.72, 3);
        var total = logo.getHeight() + gap + heightOf(dc, Graphics.FONT_SMALL, titleText);

        if (detailText != null) {
            total += gap / 3 + heightOf(dc, Graphics.FONT_XTINY, detailText);
        }

        var y = top(dc, total);

        y += bitmap(dc, y, logo) + gap;
        y += text(dc, y, Graphics.FONT_SMALL, PAPER, titleText);

        if (detailText != null) {
            text(dc, y + gap / 3, Graphics.FONT_XTINY, MUTED, detailText);
        }
    }

    // Where the options button sits in the top right corner of a bike computer's screen: left, top, width, and height.
    // Garmin's own pages put their three dots there.
    // It sits in the middle of a band of the given height at the top, and fits in it.
    function optionsBox(width, band) {
        var tall = (width * 0.09).toNumber();
        var gap = (width * 0.03).toNumber();

        if (tall > band * 0.6) {
            tall = (band * 0.6).toNumber();
        }

        var wide = (tall * 0.62).toNumber();

        return [width - gap - wide, (band - tall) / 2, wide, tall];
    }

    // The band at the top of a plain screen that holds the options button.
    function optionsBand(width) {
        return (width * 0.15).toNumber();
    }

    // Garmin's options button: three dots stacked in a framed box.
    function optionsButton(dc, box, color) {
        var x = box[0];
        var y = box[1];
        var wide = box[2];
        var tall = box[3];
        var dot = (wide * 0.12).toNumber() + 1;
        var step = tall / 4.0;

        dc.setColor(color, Graphics.COLOR_TRANSPARENT);
        dc.drawRoundedRectangle(x, y, wide, tall, 2);

        for (var index = 1; index <= 3; index++) {
            dc.fillCircle(x + wide / 2, y + (step * index).toNumber(), dot);
        }
    }

    // Whether the screen has corners for the options button.
    function hasCorners() {
        return System.getDeviceSettings().screenShape == System.SCREEN_SHAPE_RECTANGLE;
    }

    // The primary action, in the words of the sport.
    function verb(sport) {
        if (sport.equals("run") || sport.equals("trail_run")) {
            return "Run it";
        }

        if (sport.equals("hike")) {
            return "Hike it";
        }

        if (sport.equals("walk")) {
            return "Walk it";
        }

        return "Ride it";
    }

    // A small sport tile for a menu row. A route saved in the device's Courses gets the tile in green.
    function sportIcon(sport, saved) {
        if (sport.equals("road") || sport.equals("gravel")) {
            return saved ? Rez.Drawables.SportRoadSaved : Rez.Drawables.SportRoad;
        }

        if (sport.equals("mtb")) {
            return saved ? Rez.Drawables.SportMtbSaved : Rez.Drawables.SportMtb;
        }

        return saved ? Rez.Drawables.SportFootSaved : Rez.Drawables.SportFoot;
    }
}

// The wordmark at the top of a menu. A bike computer draws menus on white, so there it sits on an ink band, with the
// options button in its right corner where a tap can reach it.
class WordmarkTitle extends WatchUi.Drawable {
    var _logo;
    var _band;
    var _dots;

    function initialize() {
        Drawable.initialize({});
        _band = Brand.hasCorners();
        _dots = Rows.hasDots();
        _logo = WatchUi.loadResource(_band ? Rez.Drawables.Wordmark : Rez.Drawables.WordmarkSmall);
    }

    function draw(dc) {
        if (_band) {
            dc.setColor(Brand.INK, Brand.INK);
            dc.fillRectangle(0, 0, dc.getWidth(), dc.getHeight());
        }

        dc.drawBitmap((dc.getWidth() - _logo.getWidth()) / 2, (dc.getHeight() - _logo.getHeight()) / 2, _logo);

        if (_dots) {
            Brand.optionsButton(dc, Brand.optionsBox(dc.getWidth(), dc.getHeight()), Brand.PAPER);
        }
    }
}
