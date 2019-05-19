'use strict';

module.exports =

/**
 * Tests the user's PHP setup to see if it is properly usable.
 */
class ConfigTester
{
    /**
     * Constructor.
     *
     * @param {PhpInvoker} phpInvoker
    */
    constructor(phpInvoker) {
        this.phpInvoker = phpInvoker;
    }

    /**
     * @return {Promise}
    */
    test() {
        return new Promise((resolve) => {
            const process = this.phpInvoker.invoke(['-v']);

            process.on('close', (code) => {
                if (code === 0) {
                    resolve(true);
                    return;
                }

                resolve(false);
            });
        });
    }
};
