/**
 * These are the score bands, shared by the control panel's Score badge and the
 * front-end toolbar. A score is good from 90, fair from 70, and poor below that.
 */
const GOOD = 90;
const FAIR = 70;

/** Returns a score's band: 'good', 'fair' or 'poor'. */
export const band = (score) => (score >= GOOD ? 'good' : score >= FAIR ? 'fair' : 'poor');
