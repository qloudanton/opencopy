import Axios from 'axios';

/**
 * Configured axios instance for making API requests.
 *
 * This instance is configured to:
 * - Automatically include the XSRF-TOKEN cookie as X-XSRF-TOKEN header (CSRF protection)
 * - Include credentials (cookies) with requests
 * - Set proper Accept headers for JSON responses
 *
 * Usage:
 * ```ts
 * import { axios } from '@/lib/axios';
 *
 * // GET request
 * const response = await axios.get('/api/users');
 *
 * // POST request
 * const response = await axios.post('/api/users', { name: 'John' });
 * ```
 */
const axios = Axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
    withCredentials: true,
    withXSRFToken: true,
});

export { axios };
export default axios;
