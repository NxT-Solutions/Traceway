import type { APIRoute } from 'astro';

// Traceway shows no ads, so nobody may sell ad space on it. This is the IAB
// ads.txt record that says so; an ad exchange then refuses anyone who claims
// to sell traceway.online.
export const GET: APIRoute = () =>
    new Response('placeholder.example.com, placeholder, DIRECT, placeholder\n', {
        headers: { 'Content-Type': 'text/plain; charset=utf-8' },
    });
