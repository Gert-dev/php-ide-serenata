'use strict';

const Popover = require('./Popover');

module.exports =

/**
 * Popover that is attached to an HTML element.
 *
 * NOTE: The reason we do not use Atom's native tooltip is because it is attached to an element, which caused strange
 * problems such as tickets #107 and #72. This implementation uses the same CSS classes and transitions but handles the
 * displaying manually as we don't want to attach/detach, we only want to temporarily display a popover on mouseover.
 */
class AttachedPopover extends Popover
{
    /**
     * Constructor.
     *
     * @param {HTMLElement} elementToAttachTo The element to show the popover over.
     */
    constructor(elementToAttachTo) {
        super();

        this.timeoutId = null;
        this.elementToAttachTo = elementToAttachTo;
    }

    /**
     * Destructor.
     */
    destructor() {
        if (this.timeoutId) {
            clearTimeout(this.timeoutId);
            this.timeoutId = null;
        }

        super.destructor();
    }

    /**
     * Shows the popover with the specified text.
     */
    show() {
        const coordinates = this.elementToAttachTo.getBoundingClientRect();
        const centerOffset = ((coordinates.right - coordinates.left) / 2);

        let x = (coordinates.left - (this.getElement().offsetWidth / 2)) + centerOffset;
        let y = coordinates.bottom;

        x = Math.max(x, 0);
        y = Math.max(y, 0);

        return super.show(x, y);
    }

    /**
     * Shows the popover with the specified text after the specified delay (in miliiseconds). Calling this method
     * multiple times will cancel previous show requests and restart.
     *
     * @param {Number} delay The delay before the tooltip shows up (in milliseconds).
     */
    showAfter(delay) {
        this.timeoutId = setTimeout(() => {
            return this.show();
        }, delay);
    }
};
