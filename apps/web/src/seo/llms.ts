import { absolute, description, devices, multisport, name, repository, sports } from './site';

/** The llms.txt index: what Traceway is, and where to read more. */
export const llms = (site: URL | undefined): string => `# ${name}

> ${description}

${name} plans loop routes from a start pin. You pick a sport, a distance, a surface, and how hilly or quiet it should be. The route comes from OpenStreetMap roads and paths, never from a guess, and it loads onto a watch or bike computer that then shows the way without a phone.

- Sports: ${sports.join(', ')}.
- Multisport plans: ${multisport.join(', ')}, at race distances from a super sprint to a full Ironman, or with a distance per leg.
- Devices: ${devices.join(', ')}, and most others that can load a route. Garmin also gets turn-by-turn directions.
- A via point bends the loop through a place. No loop doubles back on itself.
- No feed, no followers, no public routes. Planning needs an account.

## Pages

- [Home](${absolute(site, '/index.md')}): what it does, the sports, multisport, devices, and the questions people ask
- [Open the planner](${absolute(site, '/dashboard')}): plan, save, and export routes (sign-in required)
- [Create an account](${absolute(site, '/register')})

## Optional

- [Source code](${repository}): open source, MIT licence
- [Full text for AI tools](${absolute(site, '/llms-full.txt')})
`;
