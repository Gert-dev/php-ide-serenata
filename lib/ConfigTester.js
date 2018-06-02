/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let ConfigTester;
const child_process = require('child_process');

module.exports =

//#*
// Tests the user's PHP setup to see if it is properly usable.
//#
(ConfigTester = class ConfigTester {
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
        return new Promise((resolve, reject) => {
            const process = this.phpInvoker.invoke(['-v']);
            return process.on('close', code => {
                if (code === 0) {
                    resolve(true);
                }

                return resolve(false);
            });
        });
    }
});
