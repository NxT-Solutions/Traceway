import Toybox.Graphics;
import Toybox.Lang;
import Toybox.System;
import Toybox.WatchUi;

// The Traceway look on a device: ink ground, paper type, and the green marker used sparingly.
module Brand {
    const INK = 0x071F20;
    const PAPER = 0xF7F5EC;
    const MARKER = 0x97CB46;
    const MUTED = 0x9AA9A6;

    function ground(dc) {
        dc.setColor(PAPER, INK);
        dc.clear();
    }

    // The wordmark, centred, with its top at y. Returns where it ends.
    function wordmark(dc, y) {
        var logo = WatchUi.loadResource(Rez.Drawables.Wordmark);
        dc.drawBitmap((dc.getWidth() - logo.getWidth()) / 2, y, logo);

        return y + logo.getHeight();
    }

    // Text centred on x, wrapped to a share of the screen width. Returns the height it took.
    function text(dc, y, font, color, value, widthShare, lines) {
        var width = (dc.getWidth() * widthShare).toNumber();
        var fitted = Graphics.fitTextToArea(value, font, width, dc.getFontHeight(font) * lines, true);

        dc.setColor(color, Graphics.COLOR_TRANSPARENT);
        dc.drawText(dc.getWidth() / 2, y, font, fitted, Graphics.TEXT_JUSTIFY_CENTER);

        return dc.getTextDimensions(fitted, font)[1];
    }

    // A screen with the wordmark and one message under it.
    function message(dc, title, detail) {
        ground(dc);

        var height = dc.getHeight();
        var bottom = wordmark(dc, (height * 0.22).toNumber());

        text(dc, bottom + (height * 0.1).toNumber(), Graphics.FONT_SMALL, PAPER, title, 0.78, 2);

        if (detail != null) {
            text(dc, bottom + (height * 0.1).toNumber() + dc.getFontHeight(Graphics.FONT_SMALL) * 2, Graphics.FONT_XTINY, MUTED, detail, 0.72, 3);
        }
    }

    // A small sport tile for a menu row.
    function sportIcon(sport) {
        if (sport.equals("road") || sport.equals("gravel")) {
            return Rez.Drawables.SportRoad;
        }

        if (sport.equals("mtb")) {
            return Rez.Drawables.SportMtb;
        }

        return Rez.Drawables.SportFoot;
    }

    function sportIconLarge(sport) {
        if (sport.equals("road") || sport.equals("gravel")) {
            return Rez.Drawables.SportRoadLarge;
        }

        if (sport.equals("mtb")) {
            return Rez.Drawables.SportMtbLarge;
        }

        return Rez.Drawables.SportFootLarge;
    }
}

// The wordmark at the top of a menu. A bike computer draws menus on white, so there it sits on an ink band.
class WordmarkTitle extends WatchUi.Drawable {
    var _logo;
    var _band;

    function initialize() {
        Drawable.initialize({});
        _band = System.getDeviceSettings().screenShape == System.SCREEN_SHAPE_RECTANGLE;
        _logo = WatchUi.loadResource(_band ? Rez.Drawables.Wordmark : Rez.Drawables.WordmarkSmall);
    }

    function draw(dc) {
        if (_band) {
            dc.setColor(Brand.INK, Brand.INK);
            dc.fillRectangle(0, 0, dc.getWidth(), dc.getHeight());
        }

        dc.drawBitmap((dc.getWidth() - _logo.getWidth()) / 2, (dc.getHeight() - _logo.getHeight()) / 2, _logo);
    }
}
