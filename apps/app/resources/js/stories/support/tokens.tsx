import tokens from '../../../design-system/tokens.json';

type ThemedValue = string | { light: string; dark?: string };

type Token = { name: string; value: ThemedValue; usage?: string };

type TextStyle = {
    name: string;
    fontSize: string;
    lineHeight: number | string;
    fontWeight: number;
    letterSpacing?: string;
    sample?: string;
    usage?: string;
};

/**
 * The docs page stays on Storybook's own light ground, so each block brings the app's ground and text with it:
 * under Ink, text in `foreground` is light and would vanish on the docs page. `sb-unstyled` keeps Storybook's docs
 * styles (its own `code` colour) out of the block.
 */
const surface =
    'sb-unstyled not-prose rounded-xl bg-background p-4 font-sans text-foreground';

const light = (value: ThemedValue) =>
    typeof value === 'string' ? value : value.light;

const dark = (value: ThemedValue) =>
    typeof value === 'string' ? value : (value.dark ?? value.light);

/** The colour tokens whose names start with one of `prefixes`, or every one when there are none. */
function colors(prefixes: string[] = []): Token[] {
    const all = tokens.color.tokens as Token[];

    return prefixes.length === 0
        ? all
        : all.filter((token) =>
              prefixes.some((prefix) => token.name.startsWith(prefix)),
          );
}

/**
 * Colour swatches straight from tokens.json, the design system's source, in Paper and Ink side by side. The
 * design-system build fails when a value here stops matching app.css or planner.css.
 */
export function ColorTokens({ names }: { names?: string[] }) {
    const list = names
        ? (tokens.color.tokens as Token[]).filter((token) =>
              names.includes(token.name),
          )
        : colors();

    return (
        <div className={`${surface} grid gap-3 text-sm sm:grid-cols-2`}>
            {list.map((token) => (
                <div
                    key={token.name}
                    className="flex min-w-0 gap-3 rounded-lg border border-border p-3"
                >
                    <div
                        className="flex h-14 w-20 shrink-0 overflow-hidden rounded-md border border-border"
                        aria-hidden="true"
                    >
                        <span
                            className="flex-1"
                            style={{ background: light(token.value) }}
                        />
                        <span
                            className="flex-1"
                            style={{ background: dark(token.value) }}
                        />
                    </div>
                    <div className="min-w-0">
                        <code className="font-semibold text-foreground">
                            --{token.name}
                        </code>
                        <p className="m-0 text-xs text-muted-foreground tabular-nums">
                            {light(token.value)}
                            {dark(token.value) !== light(token.value)
                                ? ` · ${dark(token.value)}`
                                : ''}
                        </p>
                        <p className="m-0 mt-1 text-xs">{token.usage}</p>
                    </div>
                </div>
            ))}
        </div>
    );
}

/** Every text style in tokens.json, set in Outfit at its real size. */
export function TypeScale() {
    return (
        <div className={`${surface} flex flex-col gap-6`}>
            {tokens.type.groups.map((group) => (
                <section key={group.name} className="flex flex-col gap-4">
                    <h2 className="m-0 text-sm font-medium text-muted-foreground">
                        {group.name}
                    </h2>
                    {(group.styles as TextStyle[]).map((style) => (
                        <div
                            key={style.name}
                            className="grid gap-1 border-t border-border pt-3 md:grid-cols-[14rem_1fr]"
                        >
                            <div className="text-xs text-muted-foreground tabular-nums">
                                <code className="font-semibold text-foreground">
                                    {style.name}
                                </code>
                                <br />
                                {style.fontSize} / {style.lineHeight} ·{' '}
                                {style.fontWeight}
                                {style.letterSpacing
                                    ? ` · ${style.letterSpacing}`
                                    : ''}
                            </div>
                            <div className="min-w-0">
                                <p
                                    className="m-0 tabular-nums"
                                    style={{
                                        fontSize: style.fontSize,
                                        lineHeight: style.lineHeight,
                                        fontWeight: style.fontWeight,
                                        letterSpacing: style.letterSpacing,
                                        textTransform:
                                            style.name === 'rail'
                                                ? 'uppercase'
                                                : undefined,
                                    }}
                                >
                                    {style.sample}
                                </p>
                                <p className="m-0 mt-1 text-xs text-muted-foreground">
                                    {style.usage}
                                </p>
                            </div>
                        </div>
                    ))}
                </section>
            ))}
        </div>
    );
}

/** Spacing steps and radii from tokens.json, drawn to scale. */
export function SpaceAndRadius() {
    return (
        <div className={`${surface} grid gap-8 text-sm md:grid-cols-2`}>
            <section className="flex flex-col gap-2">
                <h2 className="m-0 text-sm font-medium text-muted-foreground">
                    Spacing
                </h2>
                {(tokens.spacing.tokens as Token[]).map((token) => (
                    <div
                        key={token.name}
                        className="grid grid-cols-[6rem_1fr] items-center gap-3"
                    >
                        <code className="text-foreground">{token.name}</code>
                        <div className="flex items-center gap-2">
                            <span
                                className="h-3 rounded-sm bg-primary"
                                style={{ width: light(token.value) }}
                                aria-hidden="true"
                            />
                            <span className="text-xs text-muted-foreground">
                                {light(token.value)} · {token.usage}
                            </span>
                        </div>
                    </div>
                ))}
            </section>
            <section className="flex flex-col gap-3">
                <h2 className="m-0 text-sm font-medium text-muted-foreground">
                    Radius
                </h2>
                {(tokens.radius.tokens as Token[]).map((token) => (
                    <div key={token.name} className="flex items-center gap-3">
                        <span
                            className="size-12 shrink-0 border-2 border-foreground bg-muted"
                            style={{ borderRadius: light(token.value) }}
                            aria-hidden="true"
                        />
                        <span>
                            <code className="text-foreground">
                                {token.name}
                            </code>{' '}
                            <span className="text-xs text-muted-foreground">
                                {light(token.value)} · {token.usage}
                            </span>
                        </span>
                    </div>
                ))}
            </section>
        </div>
    );
}
