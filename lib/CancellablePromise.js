'use strict';

module.exports =

/**
 * Promise that can be cancelled.
 */
class CancellablePromise //extends Promise
{
    /**
     * Constructor.
     *
     * @param {Callable} executor
     * @param {Callable} cancelHandler
     */
    constructor(executor, cancelHandler)
    {
        this.isDone = false;
        this.promise = new Promise(executor);

        if (cancelHandler) {
            this.cancelHandler = cancelHandler;
        } else {
            this.cancelHandler = () => {
                // this.promise.reject('Promise cancelled');
            };
        }
    }

    /**
     * Cancels the promise.
     */
    cancel()
    {
        if (this.isDone !== true) {
            this.cancelHandler.call(this);
        }
    }

    /**
     * @param {callable} onFulfilled
     * @param {callable} onRejected
     *
     * @return {Promise}
     */
    then(onFulfilled, onRejected = undefined)
    {
        return this.promise.then(onFulfilled, onRejected);
    }

    /**
     * @param {callable} onRejected
     *
     * @return {Promise}
     */
    catch(onRejected)
    {
        return this.promise.catch(onRejected);
    }

    /**
     * @param {*} value
     *
     * @return {Promise}
     */
    resolve(value)
    {
        this.isDone = true;

        return this.promise.resolve(value);
    }

    /**
     * @param {*} reason
     *
     * @return {Promise}
     */
    reject(reason)
    {
        this.isDone = true;

        return this.promise.reject(reason);
    }
};
