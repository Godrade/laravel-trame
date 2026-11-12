/**
 * Editor features live in their own module and are merged into the Alpine component.
 *
 * A feature exports a function ({ cfg, D, api }) => object. The object's properties,
 * methods and getters are added to the component (names must not clash), and its
 * optional `init()` runs once the editor is ready (definitions and content loaded).
 * Features react to window events: trame:loaded, trame:changed, trame:saved, trame:published.
 */
import autosave from './autosave.js';
import revisions from './revisions.js';
import patterns from './patterns.js';
import links from './links.js';
import checks from './checks.js';

const FEATURES = { autosave, revisions, patterns, links, checks };

export function withFeatures(component, context) {
    const inits = [];

    for (const [name, feature] of Object.entries(FEATURES)) {
        const { init, ...members } = Object.getOwnPropertyDescriptors(feature(context));

        for (const key of Object.keys(members)) {
            if (key in component) {
                throw new Error(`Trame feature "${name}" redefines "${key}".`);
            }
        }

        Object.defineProperties(component, members);

        if (init?.value) {
            inits.push(init.value);
        }
    }

    Object.defineProperty(component, 'runFeatureInits', {
        value() {
            inits.forEach((init) => init.call(this));
        },
        enumerable: true,
    });

    return component;
}
