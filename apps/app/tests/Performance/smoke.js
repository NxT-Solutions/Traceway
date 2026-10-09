import http from 'k6/http';
import { check, sleep } from 'k6';

const base = __ENV.BASE_URL || 'https://traceway.ddev.site:8443';

export const options = {
    vus: 2,
    duration: '8s',
    thresholds: {
        http_req_failed: ['rate<0.05'],
        http_req_duration: ['p(95)<800'],
    },
};

export default function smoke() {
    const up = http.get(`${base}/up`);
    const home = http.get(`${base}/`);
    const login = http.get(`${base}/login`);

    check(up, { 'health is 200': (response) => response.status === 200 });
    check(home, { 'home is 200': (response) => response.status === 200 });
    check(login, { 'login is 200': (response) => response.status === 200 });

    sleep(0.2);
}
