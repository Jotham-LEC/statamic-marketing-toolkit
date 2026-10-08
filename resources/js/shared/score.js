/**
 * The score bands, shared by the control panel's Score badge and the
 * front-end toolbar: good from 90, fair from 70, poor below.
 */
export const GOOD = 90;
export const FAIR = 70;

/** A score's band: 'good', 'fair' or 'poor'. */
export const band = (score) => (score >= GOOD ? 'good' : score >= FAIR ? 'fair' : 'poor');
