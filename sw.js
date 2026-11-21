// Service worker mínimo: permite mostrar notificaciones del pedido en el celular.
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));
self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = (e.notification.data && e.notification.data.url) || './';
  e.waitUntil(self.clients.matchAll({type: 'window', includeUncontrolled: true}).then(cs => {
    for (const c of cs) if (c.url.includes('pedido.php') && 'focus' in c) return c.focus();
    return self.clients.openWindow(url);
  }));
});
