/**
 * CRO Animation System
 * 
 * Handles popup entrance and exit animations
 * Respects prefers-reduced-motion
 */
const MEYVCAnimations = {
    
    /**
     * Check if reduced motion is preferred
     */
    prefersReducedMotion: function() {
        return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    },
    
    /**
     * Animate element in
     */
    animateIn: function(element, animation = 'fade', callback) {
        if (this.prefersReducedMotion()) {
            element.style.opacity = '1';
            if (callback) callback();
            return;
        }
        
        element.classList.add('meyvc-animating', `meyvc-animate-in--${animation}`);
        
        const onEnd = () => {
            element.classList.remove('meyvc-animating', `meyvc-animate-in--${animation}`);
            element.removeEventListener('animationend', onEnd);
            if (callback) callback();
        };
        
        element.addEventListener('animationend', onEnd);
    },
    
    /**
     * Animate element out
     */
    animateOut: function(element, animation = 'fade', callback) {
        if (this.prefersReducedMotion()) {
            element.style.opacity = '0';
            if (callback) callback();
            return;
        }
        
        element.classList.add('meyvc-animating', `meyvc-animate-out--${animation}`);
        
        const onEnd = () => {
            element.classList.remove('meyvc-animating', `meyvc-animate-out--${animation}`);
            element.removeEventListener('animationend', onEnd);
            if (callback) callback();
        };
        
        element.addEventListener('animationend', onEnd);
    },
};

window.MEYVCAnimations = MEYVCAnimations;
