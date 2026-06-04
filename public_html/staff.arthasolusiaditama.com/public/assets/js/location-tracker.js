class LocationTracker {
    constructor() {
        this.isTracking = false;
        this.watchId = null;
        this.trackingInterval = null;
        this.settings = null;
        this.attendanceId = null;
        this.offlineQueue = [];
        this.lastPosition = null;
        this.isOnline = navigator.onLine;
        
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());
        
        this.loadOfflineQueue();
    }
    
    async startTracking(attendanceId) {
        this.attendanceId = attendanceId;
        
        try {
            const settingsUrl = '../app/action/tracking-get-settings.php';
            
            const response = await fetch(settingsUrl, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('[LocationTracker] Settings error response:', errorText);
                throw new Error('Failed to get tracking settings: ' + response.status + ' - ' + errorText);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                console.error('[LocationTracker] Settings returned success=false:', data);
                throw new Error(data.message || 'Settings request failed');
            }
            
            if (!data.settings.should_track) {
                console.warn('[LocationTracker] Tracking not enabled for user');
                return false;
            }
            
            this.settings = data.settings;
            
            const sessionUrl = '../app/action/tracking-start-session.php';
            
            const sessionResponse = await fetch(sessionUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attendance_id: attendanceId })
            });
            
            if (!sessionResponse.ok) {
                const errorText = await sessionResponse.text();
                console.error('[LocationTracker] Session error response:', errorText);
                throw new Error('Failed to start session: ' + sessionResponse.status + ' - ' + errorText);
            }
            
            const sessionData = await sessionResponse.json();
            
            if (!sessionData.success) {
                console.error('[LocationTracker] Session returned success=false:', sessionData);
                throw new Error(sessionData.message || 'Failed to start tracking session');
            }
            
            if (!navigator.geolocation) {
                console.error('[LocationTracker] Browser does not support geolocation');
                if (typeof showToast === 'function') showToast('Browser Anda tidak mendukung GPS tracking', 'error');
                return false;
            }
            
            this.isTracking = true;
            this.startPeriodicTracking();
            
            this.showNotification('GPS Tracking Aktif', 'Lokasi Anda sedang dilacak selama jam kerja');
            
            return true;
            
        } catch (error) {
            console.error('[LocationTracker] ========================================');
            console.error('[LocationTracker] START TRACKING ERROR');
            console.error('[LocationTracker] Error:', error);
            console.error('[LocationTracker] Error message:', error.message);
            console.error('[LocationTracker] Error stack:', error.stack);
            console.error('[LocationTracker] ========================================');
            
            return false;
        }
    }
    
    async stopTracking() {
        if (!this.isTracking) {
            return;
        }
        
        try {
            
            if (this.watchId) {
                navigator.geolocation.clearWatch(this.watchId);
                this.watchId = null;
            }
            
            if (this.trackingInterval) {
                clearInterval(this.trackingInterval);
                this.trackingInterval = null;
            }
            
            this.isTracking = false;
            
            const response = await fetch('../app/action/tracking-stop-session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attendance_id: this.attendanceId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.showNotification('GPS Tracking Dihentikan', 'Tracking selesai - Total jarak: ' + (data.total_distance || 0) + ' km');
            }
            
        } catch (error) {
            console.error('[LocationTracker] Stop tracking error:', error);
        }
    }
    
    startPeriodicTracking() {
        const intervalSeconds = this.settings.tracking_interval_seconds || 300; 
        
        this.captureLocation();
        
        this.trackingInterval = setInterval(() => {
            if (this.isTracking && !this.isBreakTime()) {
                this.captureLocation();
            }
        }, intervalSeconds * 1000);
    }
    
    isBreakTime() {
        if (!this.settings) return false;
        
        const now = new Date();
        const currentTime = now.getHours().toString().padStart(2, '0') + ':' + 
                          now.getMinutes().toString().padStart(2, '0') + ':00';
        
        return (currentTime >= this.settings.break_start_time && 
                currentTime <= this.settings.break_end_time);
    }
    
    captureLocation() {
        if (!navigator.geolocation) {
            console.error('[LocationTracker] Geolocation not available');
            return;
        }
        
        navigator.geolocation.getCurrentPosition(
            (position) => this.handlePosition(position),
            (error) => this.handleError(error),
            {
                enableHighAccuracy: this.settings.tracking_mode === 'high_accuracy',
                timeout: 10000,
                maximumAge: 30000
            }
        );
    }
    
    async handlePosition(position) {
        const location = {
            latitude: position.coords.latitude,
            longitude: position.coords.longitude,
            accuracy: position.coords.accuracy,
            speed: position.coords.speed,
            heading: position.coords.heading,
            battery: await this.getBatteryLevel(),
            timestamp: new Date().toISOString(),
            attendance_id: this.attendanceId
        };
        
        this.lastPosition = location;
        
        if (this.isOnline) {
            this.sendLocation(location);
        } else {
            this.queueLocation(location);
        }
    }
    
    handleError(error) {
        console.error('[LocationTracker] Geolocation error:', error);
        console.error('[LocationTracker] Error code:', error.code);
        console.error('[LocationTracker] Error message:', error.message);
        
        switch(error.code) {
            case error.PERMISSION_DENIED:
                console.error('[LocationTracker] Permission denied by user');
                if (typeof showToast === 'function') showToast('Anda harus mengizinkan akses lokasi untuk GPS tracking', 'error');
                this.stopTracking();
                break;
            case error.POSITION_UNAVAILABLE:
                console.warn('[LocationTracker] Location unavailable');
                break;
            case error.TIMEOUT:
                console.warn('[LocationTracker] Location timeout');
                break;
        }
    }
    
    async sendLocation(location) {
        try {
            const url = '../app/action/tracking-save-location.php';
            
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(location)
            });
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('[LocationTracker] Server error response:', errorText);
                
                try {
                    const errorJson = JSON.parse(errorText);
                    console.error('[LocationTracker] Error details:', errorJson);
                    if (errorJson.details) {
                        console.error('[LocationTracker] Database error:', errorJson.details);
                    }
                } catch (e) {
                    console.error('[LocationTracker] Raw error:', errorText.substring(0, 500));
                }
                
                throw new Error('Server error: ' + response.status + ' - ' + errorText.substring(0, 200));
            }
            
            const data = await response.json();
            
            if (data.success) {
                if (data.geofence_alert) {
                    this.showNotification(
                        'Peringatan Geofence',
                        `Anda berada di luar area ${data.geofence_alert.zone_name} (${data.geofence_alert.distance}m)`
                    );
                }
            } else {
                console.warn('[LocationTracker] Failed to save location:', data.message);
                
                if (response.status >= 500) {
                    this.queueLocation(location);
                }
            }
            
        } catch (error) {
            console.error('[LocationTracker] Send location error:', error);
            
            this.queueLocation(location);
        }
    }
    
    queueLocation(location) {
        this.offlineQueue.push(location);
        this.saveOfflineQueue();
    }
    
    saveOfflineQueue() {
        try {
            localStorage.setItem('gps_tracking_queue', JSON.stringify(this.offlineQueue));
        } catch (e) {
            console.error('[LocationTracker] Failed to save queue:', e);
        }
    }
    
    loadOfflineQueue() {
        try {
            const queue = localStorage.getItem('gps_tracking_queue');
            if (queue) {
                this.offlineQueue = JSON.parse(queue);
            }
        } catch (e) {
            console.error('[LocationTracker] Failed to load queue:', e);
            this.offlineQueue = [];
        }
    }
    
    async syncOfflineQueue() {
        if (this.offlineQueue.length === 0) return;
        
        try {
            const response = await fetch('../app/action/tracking-sync-offline.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ locations: this.offlineQueue })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.offlineQueue = [];
                this.saveOfflineQueue();
                
                this.showNotification('Sinkronisasi Berhasil', `${data.summary.success} lokasi berhasil disinkronkan`);
            }
            
        } catch (error) {
            console.error('[LocationTracker] Sync error:', error);
        }
    }
    
    handleOnline() {
        this.isOnline = true;
        this.syncOfflineQueue();
    }
    
    handleOffline() {
        this.isOnline = false;
    }
    
    async getBatteryLevel() {
        try {
            if ('getBattery' in navigator) {
                const battery = await navigator.getBattery();
                return Math.round(battery.level * 100);
            }
        } catch (e) {
            console.warn('[LocationTracker] Battery API not available');
        }
        return null;
    }
    
    showNotification(title, message) {
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification(title, { body: message });
        }
    }
}

window.locationTracker = new LocationTracker();
