(function() {
    'use strict';

    const ThemeManager = {
        STORAGE_KEY: 'asa_theme_preference',
        THEME_DARK: 'dark',
        THEME_LIGHT: 'light',
        
        init: function() {
            try {
                const theme = this.getTheme();
                this.applyTheme(theme, false);
                
                if (window.addEventListener) {
                    window.addEventListener('storage', this.handleStorageChange.bind(this));
                }
            } catch (e) {
                console.error('Theme initialization error:', e);
                this.applyTheme(this.THEME_LIGHT, false);
            }
        },
        
        getTheme: function() {
            const htmlData = document.documentElement.dataset.theme;
            if (htmlData === this.THEME_DARK || htmlData === this.THEME_LIGHT) {
                return htmlData;
            }
            
            try {
                const stored = localStorage.getItem(this.STORAGE_KEY);
                if (stored === this.THEME_DARK || stored === this.THEME_LIGHT) {
                    return stored;
                }
            } catch (e) {
                console.warn('localStorage not available:', e);
            }

            const htmlClasses = document.documentElement.className || '';
            if (htmlClasses.split(/\s+/).includes(this.THEME_DARK)) {
                return this.THEME_DARK;
            }

            const metaTheme = document.querySelector('meta[name="theme-mode"]');
            if (metaTheme && (metaTheme.content === this.THEME_DARK || metaTheme.content === this.THEME_LIGHT)) {
                return metaTheme.content;
            }

            return this.THEME_LIGHT;
        },
        
        applyTheme: function(theme, saveToStorage = true) {
            const isDark = theme === this.THEME_DARK;
            const html = document.documentElement;
            
            if (isDark) {
                html.classList.add(this.THEME_DARK);
                html.classList.remove(this.THEME_LIGHT);
            } else {
                html.classList.remove(this.THEME_DARK);
                html.classList.add(this.THEME_LIGHT);
            }

            html.dataset.theme = isDark ? this.THEME_DARK : this.THEME_LIGHT;

            try {
                html.style.colorScheme = isDark ? 'dark' : 'light';
            } catch (e) {
                console.warn('Unable to set color-scheme:', e);
            }
            
            let metaThemeColor = document.querySelector('meta[name="theme-color"]');
            if (!metaThemeColor) {
                metaThemeColor = document.createElement('meta');
                metaThemeColor.name = 'theme-color';
                document.head.appendChild(metaThemeColor);
            }
            metaThemeColor.content = isDark ? '#0f172a' : '#ffffff';

            let metaSupported = document.querySelector('meta[name="supported-color-schemes"]');
            if (!metaSupported) {
                metaSupported = document.createElement('meta');
                metaSupported.name = 'supported-color-schemes';
                document.head.appendChild(metaSupported);
            }
            metaSupported.content = isDark ? 'dark' : 'light';

            let metaColorScheme = document.querySelector('meta[name="color-scheme"]');
            if (!metaColorScheme) {
                metaColorScheme = document.createElement('meta');
                metaColorScheme.name = 'color-scheme';
                document.head.appendChild(metaColorScheme);
            }
            metaColorScheme.content = isDark ? 'dark' : 'light';

            let metaAppleBar = document.querySelector('meta[name="apple-mobile-web-app-status-bar-style"]');
            if (!metaAppleBar) {
                metaAppleBar = document.createElement('meta');
                metaAppleBar.name = 'apple-mobile-web-app-status-bar-style';
                document.head.appendChild(metaAppleBar);
            }
            metaAppleBar.content = isDark ? 'black-translucent' : 'default';
            
            if (saveToStorage) {
                try {
                    localStorage.setItem(this.STORAGE_KEY, theme);
                } catch (e) {
                    console.warn('Cannot save theme to localStorage:', e);
                }
            }
            
            if (window.CustomEvent) {
                const event = new CustomEvent('themeChanged', { detail: { theme: theme } });
                window.dispatchEvent(event);
            }
        },
        
        toggle: function() {
            const currentTheme = this.getTheme();
            const newTheme = currentTheme === this.THEME_DARK ? this.THEME_LIGHT : this.THEME_DARK;
            this.applyTheme(newTheme, true);
            return newTheme;
        },
        
        handleStorageChange: function(e) {
            if (e.key === this.STORAGE_KEY && e.newValue) {
                this.applyTheme(e.newValue, false);
            }
        },
        
        syncWithServer: function(serverTheme) {
            if (serverTheme === this.THEME_DARK || serverTheme === this.THEME_LIGHT) {
                this.applyTheme(serverTheme, true);
            }
        }
    };
    
    ThemeManager.init();
    
    window.ThemeManager = ThemeManager;
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            ThemeManager.init();
        });
    }
})();
