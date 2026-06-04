<?php
class SEOConfig {
    
    
    public static $siteConfig = [
        'site_name' => 'PT. Artha Solusi Aditama',
        'site_url' => 'http://localhost', // UBAH: Menggunakan localhost agar tidak terlempar ke web live
        'site_description' => 'HVACR Contractor and Specialist providing innovative climate control solutions in Batam, Indonesia',
        'site_keywords' => 'HVAC, AC, Air Conditioning, Refrigeration, HVACR, Batam, Indonesia, Installation, Maintenance, Repair, Chiller, Ducting, Energy Efficiency',
        'site_language' => 'id',
        'site_author' => 'PT. Artha Solusi Aditama',
        'site_robots' => 'index, follow',
        'site_canonical' => '',
        'og_type' => 'website',
        'twitter_card' => 'summary_large_image',
        'twitter_site' => '@arthasolusiaditama',
        'theme_color' => '#0369a1',
    ];
    
    public static function generateMetaTags($pageData = []) {
        $defaults = [
            'title' => self::$siteConfig['site_name'],
            'description' => self::$siteConfig['site_description'],
            'keywords' => self::$siteConfig['site_keywords'],
            'image' => self::$siteConfig['site_url'] . '/public/assets/images/logo.png',
            'url' => self::$siteConfig['site_url'],
            'type' => self::$siteConfig['og_type'],
            'robots' => self::$siteConfig['site_robots'],
        ];
        
        $data = array_merge($defaults, $pageData);
        
        $metaTags = '
        <title>' . htmlspecialchars($data['title']) . '</title>
        <meta name="description" content="' . htmlspecialchars($data['description']) . '">
        <meta name="keywords" content="' . htmlspecialchars($data['keywords']) . '">
        <meta name="author" content="' . self::$siteConfig['site_author'] . '">
        <meta name="robots" content="' . htmlspecialchars($data['robots']) . '">
        
        <meta property="og:type" content="' . htmlspecialchars($data['type']) . '">
        <meta property="og:url" content="' . htmlspecialchars($data['url']) . '">
        <meta property="og:title" content="' . htmlspecialchars($data['title']) . '">
        <meta property="og:description" content="' . htmlspecialchars($data['description']) . '">
        <meta property="og:image" content="' . htmlspecialchars($data['image']) . '">
        <meta property="og:site_name" content="' . htmlspecialchars(self::$siteConfig['site_name']) . '">
        <meta property="og:locale" content="id_ID">
        
        <meta name="twitter:card" content="' . self::$siteConfig['twitter_card'] . '">
        <meta name="twitter:site" content="' . self::$siteConfig['twitter_site'] . '">
        <meta name="twitter:title" content="' . htmlspecialchars($data['title']) . '">
        <meta name="twitter:description" content="' . htmlspecialchars($data['description']) . '">
        <meta name="twitter:image" content="' . htmlspecialchars($data['image']) . '">
        
        <link rel="canonical" href="' . htmlspecialchars($data['url']) . '">
        
        <script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "' . ($data['type'] === 'article' ? 'Article' : 'WebPage') . '",
            "headline": "' . htmlspecialchars($data['title']) . '",
            "description": "' . htmlspecialchars($data['description']) . '",
            "image": "' . $data['image'] . '",
            "datePublished": "' . date('Y-m-d') . '",
            "dateModified": "' . date('Y-m-d') . '",
            "author": {
                "@type": "Organization",
                "name": "' . self::$siteConfig['site_name'] . '"
            },
            "publisher": {
                "@type": "Organization",
                "name": "' . self::$siteConfig['site_name'] . '",
                "logo": {
                    "@type": "ImageObject",
                    "url": "' . self::$siteConfig['site_url'] . '/public/assets/images/logo.png"
                }
            },
            "mainEntityOfPage": {
                "@type": "WebPage",
                "@id": "' . $data['url'] . '"
            }
        }
        </script>
        ';
        
        return $metaTags;
    }
    
    
    public static function generateBreadcrumbSchema($items = []) {
        if (empty($items)) {
            $items = [
                ['name' => 'Home', 'url' => '/'],
                ['name' => 'About Us', 'url' => '/about']
            ];
        }
        
        $schema = '<script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": [';
        
        $position = 1;
        foreach ($items as $item) {
            $schema .= '
                {
                    "@type": "ListItem",
                    "position": ' . $position . ',
                    "name": "' . $item['name'] . '",
                    "item": "' . self::$siteConfig['site_url'] . $item['url'] . '"
                }';
            if ($position < count($items)) {
                $schema .= ',';
            }
            $position++;
        }
        
        $schema .= '
            ]
        }
        </script>';
        
        return $schema;
    }
    
    public static function generateLocalBusinessSchema() {
        return '<script type="application/ld+json">
        {
            "@context": "https://schema.org",
            "@type": "HVACBusiness",
            "name": "PT. Artha Solusi Aditama",
            "description": "HVACR Contractor and Specialist providing innovative climate control solutions",
            "url": "http://localhost",
            "telephone": "+62-851-8610-1125",
            "email": "info@pt-asa.com",
            "address": {
                "@type": "PostalAddress",
                "streetAddress": "Lytech Industrial Park C2 No 3",
                "addressLocality": "Batam Kota",
                "addressRegion": "Kepulauan Riau",
                "postalCode": "29444",
                "addressCountry": "ID"
            },
            "geo": {
                "@type": "GeoCoordinates",
                "latitude": "1.123456",
                "longitude": "104.123456"
            },
            "openingHoursSpecification": [
                {
                    "@type": "OpeningHoursSpecification",
                    "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
                    "opens": "08:00",
                    "closes": "17:00"
                }
            ],
            "priceRange": "$$",
            "image": "http://localhost/public/assets/images/logo.png"
        }
        </script>';
    }
}
?>