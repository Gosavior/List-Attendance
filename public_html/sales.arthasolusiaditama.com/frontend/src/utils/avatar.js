const STAFF_BASE = 'https://staff.arthasolusiaditama.com';

export function getAvatarUrl(avatar, userId) {
  if (!avatar) return null;
  if (avatar.startsWith('http')) return avatar;
  if (avatar.startsWith('storage/')) return `${STAFF_BASE}/${avatar}`;
  if (userId && /^avatar-/.test(avatar)) {
    return `${STAFF_BASE}/storage/uploads/avatar/${userId}/${avatar}`;
  }
  return null;
}
