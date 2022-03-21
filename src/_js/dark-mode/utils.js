export const delegateEvent = ( element, eventName, selector, handler ) => {
  element.addEventListener( eventName, function( event ) {
    // loop parent nodes from the target to the delegation node
    for ( var target = event.target; target && target != this; target = target.parentNode ) {
      if ( target.matches( selector ) ) {
        handler.call( target, event );
        break;
      }
    }
  }, false );
}
