import { dateFormatter, toast } from '@statamic/cms/api';
import { ref } from 'vue';

/**
 * Returns Statamic's axios instance, which sends the control panel's CSRF token.
 * @statamic/cms/api doesn't export it, so it comes from the app.
 */
export const useAxios = () => Statamic.$app.config.globalProperties.$axios;

/**
 * Formats a date and time as the control panel shows them, in the user's locale.
 */
export const formatDate = (value) => dateFormatter.format(value, { dateStyle: 'medium', timeStyle: 'short' });

/**
 * Sends the requests behind buttons. `busy` names the one that is running. When
 * a request fails, it shows what the server said (or `failed`) as a toast and
 * resolves with null.
 */
export function useRequests(failed) {
    const axios = useAxios();
    const busy = ref(null);

    async function send(action, request) {
        busy.value = action;

        try {
            return (await request(axios)).data;
        } catch (error) {
            const errors = error.response?.data?.errors;
            toast.error(errors ? Object.values(errors).flat().join(' ') : (error.response?.data?.message ?? failed));
            return null;
        } finally {
            busy.value = null;
        }
    }

    return { busy, send };
}
