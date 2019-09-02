/* global atom */

'use strict';

module.exports =

/**
 * Manages visual information around code lenses returned by the server.
 */
class CodeLensManager
{
    /**
     * Constructor.
     */
    constructor() {
        this.markers  = {};
        this.annotations = {};
        this.service = null;
    }

    /**
     * @param {TextEditor} editor
     * @param {Array}      codeLenses
     */
    process(editor, codeLenses) {
        this.removeMarkers(editor);

        codeLenses.forEach((codeLens) => {
            this.processOne(editor, codeLens);
        });
    }

    /**
     * @param {TextEditor} editor
     * @param {Object}     codeLens
     */
    processOne(editor, codeLens) {
        if (!codeLens.command) {
            // To support this, one would have to send a resolve request and show some sort of placeholder beforehand,
            // as we wouldn't know what title to show yet.
            throw new Error('Code lenses with unresolved commands are currently not supported');
        }

        const {Convert} = require('atom-languageclient');

        const range = Convert.lsRangeToAtomRange(codeLens.range);

        // Atom does weird things here and places the block marker somewhere at the end of the code, even with
        // "position" set to "before", so pretend it is just on the first line.
        range.end.row = range.start.row;

        const marker = this.registerMarker(editor, range, {
            invalidate : 'touch',
        });

        const anchorElement = document.createElement('a');
        anchorElement.innerHTML = codeLens.command.title;
        anchorElement.classList.add('badge');
        anchorElement.classList.add('badge-small');
        anchorElement.href = '#';
        anchorElement.addEventListener('click', () => {
            // TODO: To fix too long hover line, place anchor inside div and give div the nbsp's.
            console.log("TODO: Execute command via server ", codeLens.command);
        });

        // Markers are glued against the gutter by default, make sure they are indented to the level of the code.
        const paddingSpanElement = document.createElement('span');
        paddingSpanElement.innerHTML = '&nbsp;'.repeat(range.start.column);

        const wrapperElement = document.createElement('div');
        wrapperElement.appendChild(paddingSpanElement);
        wrapperElement.appendChild(anchorElement);

        editor.decorateMarker(marker, {
            type: 'block',
            // class: annotationInfo.lineNumberClass,
            item: wrapperElement,
        });
    }

    /**
     * @param {TextEditor} editor
     * @param {Range}      range
     * @param {Object}     options
     *
     * @return {Object}
     */
    registerMarker(editor, range, options) {
        const marker = editor.markBufferRange(range, options);

        const longTitle = editor.getLongTitle();

        if (!(longTitle in this.markers)) {
            this.markers[longTitle] = [];
        }

        this.markers[longTitle].push(marker);

        return marker;
    }

    /**
     * @param {TextEditor} editor
     */
    removeMarkers(editor) {
        const longTitle = editor.getLongTitle();

        for (let i in this.markers[longTitle]) {
            const marker = this.markers[longTitle][i];
            marker.destroy();
        }

        this.markers[longTitle] = [];
    }
};
