import { dateFormatter, toast } from '@statamic/cms/api';
import { ref } from 'vue';

/**
 * Statamic's axios, with the control panel's CSRF token. @statamic/cms/api
 * doesn't export it, so it comes from the app.
 */
export const useAxios = () => Statamic.$app.config.globalProperties.$axios;

/**
 * A date and time as the control panel shows them, in the user's locale.
 */
export const formatDate = (value) => dateFormatter.format(value, { dateStyle: 'medium', timeStyle: 'short' });

/**
 * Requests behind buttons: `busy` names the one running, and a failure shows
 * what the server said (or `failed`) as a toast, and resolves null.
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
