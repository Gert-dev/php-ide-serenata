{Disposable} = require 'atom'

module.exports =

##*
# Widget that can be used to display information about a certain context.
##
class Popover extends Disposable
    element: null

    ###*
     * Constructor.
    ###
    constructor: () ->
        @$ = require 'jquery'

        @element = document.createElement('div')
        @element.className = 'tooltip bottom fade php-integrator-popover'
        @element.innerHTML = "<div class='tooltip-arrow'></div><div class='tooltip-inner'></div>"

        document.body.appendChild(@element)

        super @destructor

    ###*
     * Destructor.
    ###
    destructor: () ->
        @hide()
        document.body.removeChild(@element)

    ###*
     * Retrieves the HTML element containing the popover.
     *
     * @return {HTMLElement}
    ###
    getElement: () ->
        return @element

    ###*
     * sets the text to display.
     *
     * @param {String} text
    ###
    setText: (text) ->
        @$('.tooltip-inner', @element).html(
            '<div class="php-integrator-popover-wrapper">' + text.replace(/\n\n/g, '<br/><br/>') + '</div>'
        )

    ###*
     * Shows a popover at the specified location with the specified text and fade in time.
     *
     * @param {Number} x The X coordinate to show the popover at (left).
     * @param {Number} y The Y coordinate to show the popover at (top).
    ###
    show: (x, y) ->
        @$(@element).css('left', x + 'px')
        @$(@element).css('top', y + 'px')

        @$(@element).addClass('in')
        @$(@element).css('display', 'block')

    ###*
     * Hides the tooltip, if it is displayed.
    ###
    hide: () ->
        @$(@element).removeClass('in')
        @$(@element).css('display', 'none')
