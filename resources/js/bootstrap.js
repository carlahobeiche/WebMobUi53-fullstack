import { setDefaultHeaders, setDefaultBaseUrl } from './composables/useFetchApi';

setDefaultBaseUrl('/api/v1');

function getXsrfToken() {//fonction qui lit le cookie xsrf-token
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
  return match ? decodeURIComponent(match[1]) : null;
}//match() extrait la partie qui nous intéresse

const xsrf = getXsrfToken();
if (xsrf) setDefaultHeaders({ 'X-XSRF-TOKEN': xsrf });