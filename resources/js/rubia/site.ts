import { mountBehaviors } from './behaviors';

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => mountBehaviors(), { once: true });
} else {
  mountBehaviors();
}
