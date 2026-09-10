import { http } from './http';
const ownerKey = 'cci-push-user';
export const pushSupported = () => window.isSecureContext && 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
export const needsHomeScreen = () => /iPhone|iPad|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
export const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
const owner = () => { try { return localStorage.getItem(ownerKey); } catch { return null; } };
function saveOwner(id) { try { id ? localStorage.setItem(ownerKey, String(id)) : localStorage.removeItem(ownerKey); } catch {} }
async function currentSubscription() {
    if (!('serviceWorker' in navigator)) return null;
    const registration = await navigator.serviceWorker.getRegistration('/');
    return registration?.pushManager?.getSubscription() || null;
}
export async function pushEnabled(userId) {
    return pushSupported() && Notification.permission === 'granted' && owner() === String(userId) && Boolean(await currentSubscription());
}
async function activateWorker(registration) {
    const worker = registration.installing || registration.waiting;
    if (!worker) return;
    await new Promise((resolve, reject) => {
        const finish = (error) => {
            clearTimeout(timeout);
            worker.removeEventListener('statechange', changed);
            error ? reject(error) : resolve();
        };
        const changed = () => {
            if (worker.state === 'installed') worker.postMessage('SKIP_WAITING');
            if (worker.state === 'activated') finish();
            if (worker.state === 'redundant') finish(new Error('Atualize a página e tente novamente.'));
        };
        const timeout = setTimeout(() => finish(new Error('Não foi possível ativar os avisos. Atualize a página e tente novamente.')), 20000);
        worker.addEventListener('statechange', changed);
        changed();
    });
}
export async function enablePush(publicKey, userId) {
    // Permission is requested directly by the user's button click.
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') throw new Error('Permita as notificações nas configurações do navegador para ativar os avisos.');
    const registration = await navigator.serviceWorker.register('/sw.js', { updateViaCache: 'none' });
    await activateWorker(registration);
    await navigator.serviceWorker.ready;
    let subscription = await registration.pushManager.getSubscription();
    const bytes = Uint8Array.from(atob(publicKey.replace(/-/g, '+').replace(/_/g, '/')), c => c.charCodeAt(0));
    if (subscription && subscription.options.applicationServerKey && !bytes.every((byte, i) => byte === new Uint8Array(subscription.options.applicationServerKey)[i])) {
        await subscription.unsubscribe();
        subscription = null;
    }
    subscription ||= await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: bytes });
    await http.post('/push/subscriptions', subscription.toJSON());
    saveOwner(userId);
}
export async function disablePush() {
    const subscription = await currentSubscription();
    if (subscription) {
        await http.delete('/push/subscriptions', { data: { endpoint: subscription.endpoint } });
        await subscription.unsubscribe();
    }
    saveOwner(null);
}
export async function syncPush(userId) {
    const subscription = await currentSubscription();
    if (!subscription) return;
    if (owner() !== String(userId)) {
        // A shared browser must stop delivering notifications for the previous account.
        await subscription.unsubscribe();
        saveOwner(null);
        return;
    }
    if (Notification.permission === 'granted') await http.post('/push/subscriptions', subscription.toJSON());
    else await disablePush();
}
