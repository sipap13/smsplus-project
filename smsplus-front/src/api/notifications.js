import api from './axios';

export async function fetchNotifications() {
  const res = await api.get('/notifications');
  return res.data;
}

export async function fetchNotificationCount() {
  const res = await api.get('/notifications/count');
  return res.data;
}

export async function markNotificationRead(id) {
  await api.post(`/notifications/${id}/lire`);
}

export async function markAllNotificationsRead() {
  await api.post('/notifications/lire-tout');
}

export async function deleteNotification(id) {
  await api.delete(`/notifications/${id}`);
}

export async function clearReadNotifications() {
  await api.delete('/notifications/vider');
}
