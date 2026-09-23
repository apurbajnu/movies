document.addEventListener('DOMContentLoaded', function() {
    let lazyImages = document.querySelectorAll('.lazy-poster');
    
    let imageObserver = new IntersectionObserver(function(entries, observer) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                let img = entry.target;
                let folderUrl = img.getAttribute('data-folderurl');
                
                // Target the current index.php file directly using the action parameter
                fetch('?action=get_image&url=' + encodeURIComponent(folderUrl))
                    .then(response => response.text())
                    .then(rawText => {
                        let cleanUrl = rawText.trim();
                        if (cleanUrl.startsWith('http')) {
                            img.src = cleanUrl;
                        } else {
                            console.error("Error output:", cleanUrl);
                            img.src = 'https://placehold.co/400x600/ffcccc/ff0000?text=Error';
                        }
                    })
                    .catch(() => {
                        img.src = 'https://placehold.co/400x600/ffcccc/ff0000?text=Network+Error';
                    });
                    
                observer.unobserve(img);
            }
        });
    }, { rootMargin: '0px 0px 300px 0px' });
    
    lazyImages.forEach(function(img) {
        imageObserver.observe(img);
    });
});