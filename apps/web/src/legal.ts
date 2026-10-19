import { absolute } from './seo/site';

/**
 * The legal pages, as words. Each page and its Markdown twin read these.
 * Text may hold [a link](/path) and **bold**, nothing else.
 */
export interface LegalSection {
    heading: string;
    paragraphs?: readonly string[];
    items?: readonly string[];
    after?: readonly string[];
}

export interface LegalPage {
    path: string;
    title: string;
    description: string;
    heading: string;
    intro: readonly string[];
    sections: readonly LegalSection[];
}

export const legalUpdated = '19 October 2026';

const operator = 'NxT-Solutions, Westerlo, Belgium';
const email = '[privacy@nxt-solutions.com](mailto:privacy@nxt-solutions.com)';
const repository = 'https://github.com/NxT-Solutions/Traceway';

export const privacy: LegalPage = {
    path: '/privacy',
    title: 'Privacy policy',
    description:
        'What Traceway keeps about you, why, who else handles it, and how to download or delete it.',
    heading: 'Privacy policy',
    intro: [
        'Traceway plans routes. To do that, it keeps your account and the routes you save, and no more than it needs. There are no ads, and your data is never sold. The pages of this site count visits only if you agree, as explained below.',
        'This page explains what is kept, why, who else handles it, and what you can do about it.',
    ],
    sections: [
        {
            heading: 'Who is responsible',
            paragraphs: [
                `Traceway is run by ${operator}. NxT-Solutions is the controller of your personal data under the GDPR.`,
                `For anything about your data, email ${email}.`,
            ],
        },
        {
            heading: 'What Traceway keeps, and why',
            items: [
                '**Your account.** Your name, your email address, and your password, stored only as a hash that cannot be turned back into the password. If you turn on two-step sign-in or a passkey, the secret it needs is kept too.',
                '**Your saved routes.** The name, the sport, what you asked the planner for, the line itself, its start and finish, every leg of a multisport plan, and the climb, surfaces, turns, and stops along it. Each saved route also gets a picture of the route on the map, for shared links.',
                '**Your watches.** The name you give a watch, a pairing code that works for 10 minutes, the key the watch uses to fetch your routes (stored only as a hash), and when it last did.',
                '**AI assistants you connect.** Which app you connected, the sign-in keys it uses, and when they run out.',
                '**Services you send routes to.** If you connect Wahoo or Ride with GPS, which one, the sign-in keys it gave Traceway (kept encrypted), when you connected, and which routes you sent there.',
                '**When you last used Traceway.** The time you last used the planner, the watch app, or a connected AI assistant, to the quarter of an hour, and your role, which is User unless you help run Traceway. It shows how many riders come back, and helps when you ask for help with your account. It is in your copy of your data.',
                '**Totals of what is used.** Each day, the server adds up how often each part of Traceway is used, such as routes drawn, files exported, sign-ins, and watch syncs. A total says how many times, never who.',
                '**Signing in.** While you are signed in, the server keeps a session with your IP address and the name of your browser. It keeps you signed in and helps stop someone else from using your account.',
                '**Error logs.** When something breaks, the server writes down what happened so it can be fixed. A log line can include your account number. It never includes your password.',
                '**Account email.** Traceway only emails you about your account: the welcome, confirming your address, resetting your password, a change of email or password, a copy of your data when you ask for it, and the goodbye when you delete your account. No newsletters, no marketing.',
            ],
            after: [
                'A start point is often a home. Keep that in mind before you share a route.',
            ],
        },
        {
            heading: 'Who at Traceway can see your account',
            paragraphs: [
                'A few people at NxT-Solutions help run Traceway. They can see your name, email address, and role, when you joined and last used Traceway, how many routes you saved, which watches, AI assistants, and services are linked, and which browsers are signed in, by name only and never with your IP address. They never see your password or the secrets for two-step sign-in.',
                'To help you, or to keep an account safe, they can change your name or email address, sign you out everywhere, turn off two-step sign-in, or delete your account. Deleting it works the same as when you do it yourself, and you get the same email.',
                'To answer a question, they can also open the planner as you see it. While they do, your password, two-step sign-in, passkeys, and data copy stay closed, and it does not count as you using Traceway.',
                'Each of these is written down: what was done to your account, by whom, and when. That record is kept for a year. What was done and when is in your copy of your data. If your account is deleted, the record no longer says it was yours.',
            ],
        },
        {
            heading: 'Routes you do not save',
            paragraphs: [
                'When you plan a route, the points and your settings go to the Traceway server, which asks the map services below to draw the route and read its climb and stops. A route you do not save is not stored with your account. The server keeps the answer for up to two hours, not linked to you, so the same request is quicker a second time.',
                'The planner also keeps your last plan in your own browser, so it is still there when you come back. See [cookies and storage](/cookies).',
            ],
        },
        {
            heading: 'Counting visits',
            paragraphs: [
                'The pages of this site, such as the home page, help, and this page, ask whether they may count your visit. Nothing is counted until you say yes. If you say no, nothing from Cloudflare is loaded. The planner itself never counts visits. It only keeps the daily totals of what is used, described above, which never say who.',
                'Traceway uses the counts for two things only: to know how many people visit, and to make the site better. For example, if few people read a page, it may need clearer words. The counts are never used for ads, never sold, and never shared with partners. Cloudflare counts them on behalf of NxT-Solutions, under a data processing agreement.',
                'To be exact about where it runs and what it sees:',
            ],
            items: [
                '**Where it runs:** the home page (/), Support Traceway (/support), and the legal pages: privacy (/privacy), terms (/terms), cookies (/cookies), and legal notice (/legal).',
                '**Where it never runs:** the planner and everything you reach after you log in, such as your routes, your library, and your settings. Also the log in and sign up pages, shared route links, error pages, the Traceway watch app, AI assistants connected to Traceway, and the emails Traceway sends.',
                '**What Cloudflare gets:** which page you opened, the site that sent you there, your browser and device type, and how fast the page loaded. Like any website, it sees your IP address. It sets no cookie and keeps nothing in your browser.',
                '**What Traceway sees:** only totals, such as how many people opened a page and from which country. Never your name, your email address, or your routes, and a visit is not linked to your Traceway account.',
            ],
            after: [
                'You can change your mind at any time with **Cookie choice** at the bottom of every page. After a no, nothing is counted from the next page on. It does not undo what was counted before, including the page you are on.',
            ],
        },
        {
            heading: 'Shared links',
            paragraphs: [
                'Anyone who has a shared link can see that route, its picture, and how it was planned. They do not see your name or your email address. Shared links are kept out of search engines. Delete the route, and its link stops working.',
            ],
        },
        {
            heading: 'Who else handles your data',
            paragraphs: [
                'Traceway uses a few other services. Each gets only what it needs.',
            ],
            items: [
                '**Hetzner Online** hosts the server and the database, in Germany.',
                '**Cloudflare** passes every visit to traceway.online on to the server, to keep the site fast and to stop attacks, so it sees your IP address and the pages you ask for. It checks that a person, not a script, fills in the sign-up and password reset forms. It also keeps the backup copies of the database, and counts visits to the pages of this site when you agree. NxT-Solutions reads Cloudflare’s daily totals of requests and visits, never a single visit.',
                '**Resend** sends the account email. It gets your email address and the message. For a copy of your data, that includes the file.',
                '**Sentry** tells NxT-Solutions when something in Traceway breaks or runs slowly. It gets the address of the page without its search part, your browser type and language, how long each step took, and what went wrong, which can hold the points or the place name the server was asking a map service about. It never gets your name, email address, IP address, cookies, or what you type into a form. Sentry keeps it in Germany.',
                '**Map services** draw and read the routes: a [Valhalla](https://valhalla1.openstreetmap.de) routing server run by FOSSGIS e.V. in Germany, [Nominatim](https://nominatim.org) from the OpenStreetMap Foundation for place names, and the [OpenFreeMap](https://openfreemap.org) map tiles for water, toilets, and cafés. The Traceway server sends them the points of a route, the name of a place, a place you search for with the middle of your map rounded to about 10 kilometres, a pin rounded to about 100 metres, or which map tiles a route crosses. It never sends your name or email address.',
                '**Your own browser** loads the map itself from [OpenFreeMap](https://openfreemap.org). Like any website, it sees your IP address and the part of the map you look at. Everything else goes through Traceway.',
            ],
            after: [
                'Hetzner, Cloudflare, Resend, and Sentry handle your data only on behalf of NxT-Solutions, under a data processing agreement.',
            ],
        },
        {
            heading: 'AI assistants, watches, route services, and donations',
            paragraphs: [
                'If you connect an AI assistant, such as Claude or ChatGPT, what you type there is handled by the company behind it, under its own privacy policy. Traceway gives the assistant only what you ask it for: the routes it plans for you and the list of your saved routes.',
                'The Traceway watch app fetches your routes straight from Traceway. A route file you open in Garmin Connect, or in any other app, is handled by that app’s maker.',
                'If you connect Wahoo or Ride with GPS, Traceway sends that service the route you send: its name, sport, line, distance, and climb. It sends nothing else about you. From there, the service handles the route under its own privacy policy. Both are American companies. Disconnect, or delete your account, and Traceway asks the service to withdraw its keys.',
                'Donations go through PayPal or GitHub Sponsors, on their own sites. Traceway never sees your card or bank details. They may pass your name and email address to NxT-Solutions, which keeps them only for its records.',
            ],
        },
        {
            heading: 'Outside the EU',
            paragraphs: [
                'Cloudflare, Resend, and Sentry are American companies. When your data leaves the European Economic Area, it is protected by the EU-U.S. Data Privacy Framework or by the EU’s standard contractual clauses.',
            ],
        },
        {
            heading: 'The legal grounds',
            items: [
                '**The service you asked for** (GDPR article 6(1)(b)): your account, your routes, your watches, the AI assistants and route services you connect, and the account email.',
                '**Legitimate interests** (article 6(1)(f)): keeping Traceway safe and working, with sessions, error logs and reports, speed checks, backups, protection against attacks and scripts, and limits on how often the map services are asked. Also knowing how much Traceway is used, with the time you last used it and daily totals that never say who, and helping you with your account when you ask.',
                '**Your consent** (article 6(1)(a)): counting visits to the pages of this site with Cloudflare, to know how many people visit and to make the site better. You can take it back at any time with Cookie choice at the bottom of every page.',
                '**A legal duty** (article 6(1)(c)): when the law requires NxT-Solutions to keep something or hand it over.',
            ],
        },
        {
            heading: 'How long it is kept',
            items: [
                '**Your account and saved routes:** until you delete them, or your account.',
                '**A session:** until you log out, or 2 hours after you last used Traceway. With “Remember me”, up to 400 days.',
                '**A pairing code:** 10 minutes.',
                '**When you last used Traceway:** until you delete your account.',
                '**Daily totals of what is used:** two years, not linked to you.',
                '**What the people who run Traceway did to your account:** one year.',
                '**Unsaved routes and their details:** up to two hours, not linked to you.',
                '**Place names:** 30 days, for a pin rounded to about 100 metres, not linked to you.',
                '**Place searches:** 30 days, for the words you typed and the middle of your map rounded to about 10 kilometres, not linked to you.',
                '**An email that could not be sent:** up to a day, so the problem can be found. Then it is deleted.',
                '**Your yes or no to counting visits:** in your own browser, for up to a year. Then the pages ask again.',
                '**Backups:** something you delete can stay in a backup for up to 14 days. Then it is gone.',
                '**Error logs:** only the latest. Older lines are overwritten automatically.',
                '**Error reports at Sentry:** 90 days at most. Then Sentry deletes them.',
            ],
        },
        {
            heading: 'Your rights',
            paragraphs: [
                'You can see, correct, download, and delete your data yourself, in the planner:',
            ],
            items: [
                '**See it and download it:** Settings, then Your data. The file has your account and every saved route, and a copy is emailed to you.',
                '**Correct it:** Settings, then Profile.',
                '**Delete it:** delete a route in your library, or your whole account under Settings, then Profile. It is deleted straight away.',
                '**Disconnect a watch or an AI assistant:** Settings, then Devices or AI assistants.',
            ],
            after: [
                `You also have the right to ask what is kept about you, to object to how it is used, to limit its use, and to take it with you. Email ${email}, and you will get an answer within a month.`,
                'If you think your data is handled wrongly, you can complain to the Belgian Data Protection Authority, at [dataprotectionauthority.be](https://www.dataprotectionauthority.be), or to the authority in your own country.',
            ],
        },
        {
            heading: 'Keeping it safe',
            paragraphs: [
                'Every page is sent over an encrypted connection. Passwords and watch keys are stored only as hashes. You can add two-step sign-in or a passkey under Settings, then Security.',
            ],
        },
        {
            heading: 'Children',
            paragraphs: [
                `Traceway is for people aged 13 and over. If you think a child under 13 has an account, email ${email} and it will be deleted.`,
            ],
        },
        {
            heading: 'Changes',
            paragraphs: [
                'If this policy changes, the new version is posted here with a new date. For a big change, you get an email first.',
            ],
        },
    ],
};

export const terms: LegalPage = {
    path: '/terms',
    title: 'Terms of use',
    description:
        'The agreement for using Traceway, a free route planner. Check every route yourself before you go.',
    heading: 'Terms of use',
    intro: [
        `These terms are the agreement between you and ${operator}, which runs Traceway. By using Traceway or making an account, you accept them. They are short on purpose.`,
    ],
    sections: [
        {
            heading: 'Check the route before you go',
            paragraphs: [
                'Traceway draws routes with a computer, from OpenStreetMap data. That data is made by volunteers and can be wrong or out of date. A road can be closed, busy, private, or unsafe. A path can be gone. A café can be shut, and a tap can be dry. Heights, climbs, and distances are estimates.',
                'So look over every route before you go, follow traffic rules and signs, and trust what you see over what the screen says. You decide whether a route is safe for you, and you go out at your own risk.',
            ],
        },
        {
            heading: 'Free, for everyone',
            paragraphs: [
                'Traceway is free, and there is no paid plan. A donation is a gift. It does not buy or unlock anything.',
            ],
        },
        {
            heading: 'Your account',
            paragraphs: [
                'You must be 13 or older. Use your own email address, keep your password to yourself, and tell us if you think someone else is using your account. One account is for one person.',
            ],
        },
        {
            heading: 'Your routes are yours',
            paragraphs: [
                'The routes you save stay yours. You allow Traceway to store them, work with them, and show them to you, to your watch, to the AI assistants you connect, and to anyone you give a shared link. That is all they are used for.',
                'Anyone with a shared link can see that route. Delete the route to stop the link working.',
                'Routes are made from OpenStreetMap data. If you publish a route file, credit “© OpenStreetMap contributors”.',
            ],
        },
        {
            heading: 'Use it fairly',
            items: [
                'Do not use Traceway to break the law or to harm anyone.',
                'Do not overload it. Plan automatically only through the AI connector, within its limits, and do not scrape Traceway or the map services behind it.',
                'Do not try to get around its security, or into someone else’s account.',
                'Do not put anything offensive, or someone else’s personal details, in a route you share.',
            ],
        },
        {
            heading: 'Other services',
            paragraphs: [
                'Traceway works with services it does not run: the map services, AI assistants such as Claude and ChatGPT, and watches and apps from Garmin and others. Their own terms apply to them. Traceway is not linked to, or endorsed by, any of them.',
            ],
        },
        {
            heading: 'When Traceway is down or changes',
            paragraphs: [
                'Traceway aims to stay online, but it can be down, slow, or changed, and the map services it relies on can be busy. Features can be added or taken away. If Traceway ever closes, you will be told in good time to download your routes.',
            ],
        },
        {
            heading: 'Liability',
            paragraphs: [
                'Traceway is free and offered as it is. As far as the law allows, NxT-Solutions is not liable for loss or harm from using Traceway or a route it drew, such as a wrong turn, a closed road, or a lost file.',
                'Nothing in these terms limits a liability that the law does not allow to be limited, such as for intent, gross negligence, or death or injury caused by fault. Your rights as a consumer stay as they are.',
            ],
        },
        {
            heading: 'Ending',
            paragraphs: [
                'You can stop at any time and delete your account under Settings, then Profile. NxT-Solutions can suspend or close an account that breaks these terms, after a warning where that is possible.',
            ],
        },
        {
            heading: 'Open source',
            paragraphs: [
                `The Traceway code is open source under the [MIT License](${repository}/blob/main/LICENSE). The name Traceway and its logo are not part of that license.`,
            ],
        },
        {
            heading: 'Changes to these terms',
            paragraphs: [
                'If these terms change, the new version is posted here with a new date. For a big change, you get an email first. If you keep using Traceway after that, you accept the new terms. If you do not, you can delete your account.',
            ],
        },
        {
            heading: 'Law and disputes',
            paragraphs: [
                'Belgian law applies. A dispute goes to the courts of Belgium, unless the law lets you go to the courts where you live. Email first: most problems are solved that way.',
                `Questions about these terms: ${email}.`,
            ],
        },
    ],
};

export const cookies: LegalPage = {
    path: '/cookies',
    title: 'Cookies',
    description:
        'The cookies and browser storage Traceway uses to keep you signed in and remember your choices, and the visit counting it asks you about first.',
    heading: 'Cookies and storage',
    intro: [
        'Traceway uses only the cookies it needs to work. There are no advertising, analytics, or tracking cookies, and none from another company.',
        'The pages of this site, like this one, set no cookies. They ask before they count your visit with Cloudflare Web Analytics, which sets no cookie either. The planner sets the cookies below, and never counts visits. Its daily totals of what is used need no cookie and never say who.',
    ],
    sections: [
        {
            heading: 'Cookies',
            items: [
                '**__Host-traceway-session** keeps you signed in while you use the planner. Only traceway.online can read it, and only over a secure connection. It ends when you log out, or 2 hours after you last used Traceway.',
                '**XSRF-TOKEN** protects your forms, so another site cannot send them in your name. It lasts as long as the session.',
                '**remember_web_…** is set only when you tick “Remember me”. It keeps you signed in for up to 400 days, or until you log out.',
                '**appearance** remembers light, dark, or the same as your device. It lasts a year.',
            ],
        },
        {
            heading: 'Storage in your browser',
            paragraphs: [
                'A few things are kept in your own browser’s storage. They stay on your device.',
            ],
            items: [
                '**traceway.planner…** keeps the route you were planning, so it is still there when you come back, and the last few places you picked in a search. It is cleared when you log out.',
                '**traceway.library.layout** remembers whether your library shows a grid, a map, or a list.',
                '**appearance** keeps your light or dark choice here too.',
                '**traceway.consent** remembers whether you said yes or no to counting your visit, and when, so the pages of this site do not ask again for a year.',
            ],
        },
        {
            heading: 'The map',
            paragraphs: [
                'The map itself comes from OpenFreeMap. Your browser fetches it directly, so OpenFreeMap sees your IP address, as any website does. Traceway shares nothing else about you with it, and place names come through Traceway. The [privacy policy](/privacy) has more.',
            ],
        },
        {
            heading: 'Your choice',
            paragraphs: [
                'The planner’s cookies are needed for Traceway to work, or keep a choice you made, so EU and Belgian law does not ask for your consent. You can still delete them in your browser settings at any time. You will then be logged out, and the planner forgets your choices.',
                'Counting your visit is up to you. Say yes or no when the pages of this site ask, and change your mind at any time with **Cookie choice** at the bottom of every page. The [privacy policy](/privacy) has more.',
            ],
        },
    ],
};

export const notice: LegalPage = {
    path: '/legal',
    title: 'Legal notice',
    description:
        'Who runs Traceway, how to reach them, and the map data, code, and names it relies on.',
    heading: 'Legal notice',
    intro: [],
    sections: [
        {
            heading: 'Who runs Traceway',
            paragraphs: [`Traceway is run by ${operator}.`, `Email: ${email}.`],
        },
        {
            heading: 'Open source',
            paragraphs: [
                `The code is open source under the [MIT License](${repository}/blob/main/LICENSE). Read it, run it, or help improve it on [GitHub](${repository}). Report a security problem privately, as the [security policy](${repository}/blob/main/SECURITY.md) explains.`,
                'The name Traceway and its logo are not covered by the license.',
            ],
        },
        {
            heading: 'Map data and services',
            items: [
                'Map data © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors, under the Open Database License.',
                'Map tiles by [OpenFreeMap](https://openfreemap.org) and [OpenMapTiles](https://openmaptiles.org), drawn with [MapLibre](https://maplibre.org).',
                'Routes by [Valhalla](https://github.com/valhalla/valhalla), and place names by [Nominatim](https://nominatim.org). Water, toilets, and cafés come from the OpenFreeMap tiles.',
            ],
        },
        {
            heading: 'Type and icons',
            paragraphs: [
                'Set in [Outfit](https://github.com/Outfitio/Outfit-Fonts), under the SIL Open Font License. Icons by [Lucide](https://lucide.dev), under the ISC License.',
            ],
        },
        {
            heading: 'Other names',
            paragraphs: [
                'Garmin, Connect IQ, ClimbPro, COROS, Suunto, Wahoo, Hammerhead, Bryton, Claude, ChatGPT, Codex, Cursor, and Visual Studio Code are trademarks of their owners. Traceway is not linked to, or endorsed by, any of them.',
            ],
        },
        {
            heading: 'More',
            paragraphs: [
                'How Traceway handles your data is in the [privacy policy](/privacy), the rules for using it are in the [terms of use](/terms), and what it keeps in your browser is under [cookies and storage](/cookies).',
            ],
        },
    ],
};

export const legalPages = [privacy, terms, cookies, notice] as const;

const escaped = (text: string): string =>
    text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');

/** A line of legal text as HTML: links and bold, everything else escaped. */
export const inline = (text: string): string =>
    escaped(text)
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\[(.+?)\]\((.+?)\)/g, (_, label: string, href: string) =>
            href.startsWith('/') || href.startsWith('mailto:')
                ? `<a href="${href}">${label}</a>`
                : `<a href="${href}" rel="noopener">${label}</a>`,
        );

/** A legal page as Markdown, with its site links made absolute. */
export const markdown = (page: LegalPage, site: URL | undefined): string => {
    const lines = [`# ${page.heading}`, '', `Last updated ${legalUpdated}.`];
    const block = (
        texts: readonly string[] | undefined,
        bullet = false,
    ): void => {
        for (const text of texts ?? []) {
            lines.push(...(bullet ? [] : ['']), `${bullet ? '- ' : ''}${text}`);
        }
    };

    block(page.intro);

    for (const section of page.sections) {
        lines.push('', `## ${section.heading}`);
        block(section.paragraphs);

        if (section.items) {
            lines.push('');
            block(section.items, true);
        }

        block(section.after);
    }

    return `${lines.join('\n').replace(/\]\((\/[^)]*)\)/g, (_, path: string) => `](${absolute(site, path)})`)}\n`;
};
