import CoreGraphics
import Foundation
import ImageIO
// Measures whether a button label sits in the middle of its button, from a simulator capture of a route screen.
//
//     swift tools/label_centre.swift <tall> <capture.png>...
//
// <tall> is the button height in device pixels, as RouteDetail draws it. It finds the green line under the map,
// then the green button below it, keeps the button's straight rows, and prints how far the label's middle sits
// from the button's middle, in device pixels. Anything within 0.5 is as centred as whole pixels allow.
let args = CommandLine.arguments
guard args.count > 2, let tall = Double(args[1]) else { print("usage: label_centre.swift <tall> <capture.png>..."); exit(1) }
for path in args.dropFirst(2) {
    let name = (path as NSString).lastPathComponent
    guard let src = CGImageSourceCreateWithURL(URL(fileURLWithPath: path) as CFURL, nil), let img = CGImageSourceCreateImageAtIndex(src, 0, nil) else { print("\(name): unreadable"); continue }
    let w = img.width, h = img.height
    var px = [UInt8](repeating: 0, count: w * h * 4)
    let ctx = CGContext(data: &px, width: w, height: h, bitsPerComponent: 8, bytesPerRow: w * 4, space: CGColorSpace(name: CGColorSpace.sRGB)!, bitmapInfo: CGImageAlphaInfo.premultipliedLast.rawValue)!
    ctx.draw(img, in: CGRect(x: 0, y: 0, width: w, height: h))
    func at(_ x: Int, _ y: Int) -> (Int, Int, Int) { let i = (y * w + x) * 4; return (Int(px[i]), Int(px[i + 1]), Int(px[i + 2])) }
    func green(_ c: (Int, Int, Int)) -> Bool { c.1 > 150 && c.0 > 110 && c.0 < 215 && c.2 < 130 && c.1 - c.2 > 60 }
    func dark(_ c: (Int, Int, Int)) -> Bool { c.0 + c.1 + c.2 < 200 }
    func run(_ y: Int, _ from: Int, _ to: Int) -> (Int, Int, Int) {
        var best = (0, 0, 0), start = -1
        for x in from...to { if green(at(x, y)) { if start < 0 { start = x } } else { if start >= 0 && x - start > best.2 { best = (start, x - 1, x - start) }; start = -1 } }
        if start >= 0 && to + 1 - start > best.2 { best = (start, to, to + 1 - start) }
        return best
    }
    // The marker line: the first thin row band with one long green run.
    var line = -1, lineRun = (0, 0, 0)
    for y in (h / 5)..<(h * 9 / 10) { let r = run(y, 0, w - 1); if r.2 > w / 4 { line = y; lineRun = r; break } }
    if line < 0 { print("\(name): no line under the map"); continue }
    let left = lineRun.0, right = lineRun.1
    let floor = min(h - 1, line + Int(Double(right - left) * 0.75))
    // Rows below the line with a lot of green inside the panel's width: the button, where the label breaks it up.
    var rows: [Int: (Int, Int)] = [:]
    for y in (line + 4)...floor {
        var count = 0, first = -1, last = -1
        for x in left...right where green(at(x, y)) { count += 1; if first < 0 { first = x }; last = x }
        if count > (right - left) / 12 { rows[y] = (first, last) }
    }
    var top = -1, bottom = -1
    var y = line + 4
    while y <= floor { if rows[y] != nil { var e = y; while rows[e + 1] != nil { e += 1 }; if e - y > bottom - top { top = y; bottom = e }; y = e + 1 } else { y += 1 } }
    if top < 0 { print("\(name): no button"); continue }
    // The straight part: rows nearly as wide as the widest.
    let widest = (top...bottom).map { rows[$0]!.1 - rows[$0]!.0 }.max()!
    let straight = (top...bottom).filter { rows[$0]!.1 - rows[$0]!.0 >= widest * 9 / 10 }
    let sTop = straight.first!, sBottom = straight.last!
    let span = rows[(sTop + sBottom) / 2]!
    let x0 = span.0 + (span.1 - span.0) / 5, x1 = span.1 - (span.1 - span.0) / 5
    var tTop = -1, tBottom = -1
    for yy in sTop...sBottom { for x in x0...x1 where dark(at(x, yy)) { if tTop < 0 { tTop = yy }; tBottom = yy; break } }
    if tTop < 0 { print("\(name): no label"); continue }
    let pill = sBottom - sTop + 1
    let scale = Double(bottom - top + 1) / tall
    let off = (Double(tTop + tBottom) / 2) - (Double(sTop + sBottom) / 2)
    print(String(format: "%@  button %d px, label %d px, %@ by %.1f device px", name, pill, tBottom - tTop + 1, off > 0 ? "low" : "high", abs(off) / scale))
}
