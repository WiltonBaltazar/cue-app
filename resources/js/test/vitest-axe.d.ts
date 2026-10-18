import type { AxeMatchers } from 'vitest-axe/matchers';

// vitest-axe only augments the legacy global `Vi` namespace; Vitest 4 reads
// matcher types from the `vitest` module.
declare module 'vitest' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type, @typescript-eslint/no-explicit-any
    interface Assertion<T = any> extends AxeMatchers {}
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface AsymmetricMatchersContaining extends AxeMatchers {}
}
