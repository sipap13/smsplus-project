/** Reject garbage objects so we never treat empty {} as "logged in". */
export function isValidSessionUser(u) {
  if (!u || typeof u !== 'object') return false;
  const id = u.id;
  if (id === null || id === undefined || id === '') return false;
  if (!u.role && !u.email) return false;
  return true;
}
