/*
 * decaffeinate suggestions:
 * DS102: Remove unnecessary code created because of implicit returns
 * DS206: Consider reworking classes to avoid initClass
 * DS207: Consider shorter variations of null checks
 * Full docs: https://github.com/decaffeinate/decaffeinate/blob/master/docs/suggestions.md
 */
let AttachedPopover;
const Popover = require('./Popover');

module.exports =

//#*
// Popover that is attached to an HTML element.
//
// NOTE: The reason we do not use Atom's native tooltip is because it is attached to an element, which caused strange
// problems such as tickets #107 and #72. This implementation uses the same CSS classes and transitions but handles the
// displaying manually as we don't want to attach/detach, we only want to temporarily display a popover on mouseover.
//#
(AttachedPopover = (function() {
    AttachedPopover = class AttachedPopover extends Popover {
        static initClass() {
        /**
         * Timeout ID, used for setting a timeout before displaying the popover.
        */
            this.prototype.timeoutId = null;

            /**
         * The element to attach the popover to.
        */
            this.prototype.elementToAttachTo = null;
        }

        /**
       * Constructor.
       *
       * @param {HTMLElement} elementToAttachTo The element to show the popover over.
       * @param {Number}      delay             How long the mouse has to hover over the elment before the popover shows
       *                                        up (in miliiseconds).
      */
        constructor(elementToAttachTo, delay) {
            super();

            this.elementToAttachTo = elementToAttachTo;

            if (delay == null) {
                delay = 500;
            }
        }

        /**
       * Destructor.
      */
        destructor() {
            if (this.timeoutId) {
                clearTimeout(this.timeoutId);
                this.timeoutId = null;
            }

            return super.destructor();
        }

        /**
       * Shows the popover with the specified text.
      */
        show() {
            const coordinates = this.elementToAttachTo.getBoundingClientRect();

            const centerOffset = ((coordinates.right - coordinates.left) / 2);

            let x = (coordinates.left - (this.getElement().offsetWidth / 2)) + centerOffset;
            let y = coordinates.bottom;

            if (x < 0) { x = 0; }
            if (y < 0) { y = 0; }

            return super.show(x, y);
        }

        /**
       * Shows the popover with the specified text after the specified delay (in miliiseconds). Calling this method
       * multiple times will cancel previous show requests and restart.
       *
       * @param {Number} delay The delay before the tooltip shows up (in milliseconds).
      */
        showAfter(delay) {
            return this.timeoutId = setTimeout(() => {
                return this.show();
            }
                , delay);
        }
    };
    AttachedPopover.initClass();
    return AttachedPopover;
})());
