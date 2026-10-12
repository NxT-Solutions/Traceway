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

## For AI agents

- MCP server: ${absolute(site, '/mcp')} (streamable HTTP). People sign in with OAuth 2.1; discovery is at ${absolute(site, '/.well-known/oauth-protected-resource/mcp')}, and clients register themselves.
- Tools: \`plan_loop\` (start place, sport, distance in km, and optional surface, hills, quiet roads, via), \`plan_multisport\` (a transition and a brick, triathlon, duathlon, or aquabike at a race distance, or up to four legs), and \`list_routes\`.
- Each plan is saved to the person's library, and the answer links to the map, a Garmin file, and a GPX file. Never invent route coordinates; call a tool.

## Optional

- [Source code](${repository}): open source, MIT licence
- [Full text for AI tools](${absolute(site, '/llms-full.txt')})
`;
