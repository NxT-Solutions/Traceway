import {
    DocsContainer,
    type DocsContainerProps,
} from '@storybook/addon-docs/blocks';
import { type PropsWithChildren, useEffect } from 'react';
import { ink, paper } from './theme';

type Globals = { appearance?: string };

/**
 * Docs pages follow the Appearance toolbar like the stories do. Without this the docs page stays on Storybook's
 * light theme while the stories on it switch to Ink, and their light text vanishes on it. The page also says which
 * theme it is in (`data-docs-theme`), for preview.css: a docs page whose stories each render in their own frame
 * never gets the app's `dark` class.
 */
export function ThemedDocsContainer(
    props: PropsWithChildren<DocsContainerProps>,
) {
    const store = (
        props.context as unknown as {
            store?: { userGlobals?: { globals?: Globals } };
        }
    ).store;
    const theme = store?.userGlobals?.globals?.appearance === 'dark' ? 'ink' : 'paper';

    useEffect(() => {
        document.documentElement.dataset.docsTheme = theme;
    }, [theme]);

    return <DocsContainer {...props} theme={theme === 'ink' ? ink : paper} />;
}
