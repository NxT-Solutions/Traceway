import Toybox.Graphics;
import Toybox.Lang;
import Toybox.System;
import Toybox.WatchUi;

// The route list's menu and its rows. Most devices use the system menu, with the sport tile as the row's icon. The Edge
// 530 and 830 put a menu icon in a small box near the top of its row, so no icon can sit in the middle there: on those
// the rows are drawn here, in the look of that menu, with the tile in the middle of the row (:customrows).
//
// Where the system menu puts an icon, and how big it lets it be, differs per device. Measured in the simulator on every
// device in the manifest: the box it hands an icon spans the row's height, or is a small square in the middle of the row,
// so a tile drawn in the middle of that box is in the middle of the row.
module Rows {
    (:menurows)
    function menu() {
        return new WatchUi.Menu2({ :title => new WordmarkTitle() });
    }

    (:menurows)
    function route(id, label, detail, sport, saved) {
        return new WatchUi.IconMenuItem(label, detail, id, new TileIcon(sport, saved), null);
    }

    (:menurows)
    function plain(id, label) {
        return new WatchUi.MenuItem(label, null, id, null);
    }

    (:menurows)
    function mark(item, sport, saved) {
        item.setIcon(new TileIcon(sport, saved));
    }

    // As tall as the system menu's rows on these Edges, which hold a name and a line of numbers.
    (:customrows)
    function menu() {
        return new WatchUi.CustomMenu((System.getDeviceSettings().screenHeight * 0.183).toNumber(), Graphics.COLOR_WHITE, { :title => new WordmarkTitle() });
    }

    (:customrows)
    function route(id, label, detail, sport, saved) {
        return new RouteRow(id, label, detail, sport, saved);
    }

    (:customrows)
    function plain(id, label) {
        return new RouteRow(id, label, null, null, false);
    }

    (:customrows)
    function mark(item, sport, saved) {
        item.setSaved(saved);
        WatchUi.requestUpdate();
    }
}

// A sport tile as a system menu icon, drawn in the middle of the box the menu gives it.
(:menurows)
class TileIcon extends WatchUi.Drawable {
    var _tile;

    function initialize(sport, saved) {
        _tile = WatchUi.loadResource(Brand.sportIcon(sport, saved));
        Drawable.initialize({ :width => _tile.getWidth(), :height => _tile.getHeight() });
    }

    function draw(dc) {
        // Rounded down, not up: the box some menus give is a pixel short of the row, at its top.
        dc.drawBitmap((dc.getWidth() - _tile.getWidth()) / 2, (dc.getHeight() - _tile.getHeight() + 1) / 2, _tile);
    }
}

// A row of the drawn list: white, or the system menu's blue when it has the focus, with the name and its numbers on the
// left and the sport tile in the middle of the row on the right.
(:customrows)
class RouteRow extends WatchUi.CustomMenuItem {
    const FOCUS = 0x0275BD;
    const LINE = 0xD9D9D9;

    var _label;
    var _detail;
    var _sport;
    var _tile = null;

    function initialize(id, label, detail, sport, saved) {
        CustomMenuItem.initialize(id, {});
        _label = label;
        _detail = detail;
        _sport = sport;
        setSaved(saved);
    }

    function setSaved(saved) {
        _tile = _sport == null ? null : WatchUi.loadResource(Brand.sportIcon(_sport, saved));
    }

    function draw(dc) {
        var width = dc.getWidth();
        var height = dc.getHeight();
        var focused = isFocused();
        var margin = (width * 0.05).toNumber();

        dc.setColor(focused ? FOCUS : Graphics.COLOR_WHITE, focused ? FOCUS : Graphics.COLOR_WHITE);
        dc.fillRectangle(0, 0, width, height);

        if (!focused) {
            dc.setColor(LINE, LINE);
            dc.drawLine(0, height - 1, width, height - 1);
        }

        if (_tile != null) {
            dc.drawBitmap(width - margin - _tile.getWidth(), (height - _tile.getHeight()) / 2, _tile);
        }

        var ink = focused ? Graphics.COLOR_WHITE : Graphics.COLOR_BLACK;
        var labelHeight = Graphics.getFontHeight(Graphics.FONT_MEDIUM);
        var detailHeight = _detail == null ? 0 : Graphics.getFontHeight(Graphics.FONT_SMALL);
        var y = (height - labelHeight - detailHeight) / 2;

        dc.setColor(ink, Graphics.COLOR_TRANSPARENT);
        dc.drawText(margin, y, Graphics.FONT_MEDIUM, _label, Graphics.TEXT_JUSTIFY_LEFT);

        if (_detail != null) {
            dc.drawText(margin, y + labelHeight, Graphics.FONT_SMALL, _detail, Graphics.TEXT_JUSTIFY_LEFT);
        }
    }
}
