/**
 * How the rider last logged in on this browser. The comma is for screen readers, after the button's own words.
 * Outside a button, give it an id and point the field at it with aria-describedby.
 */
export default function LastUsed({ id }: { id?: string }) {
    return (
        <span id={id} className="last-used">
            <span className="vh">, </span>
            Last used
        </span>
    );
}
