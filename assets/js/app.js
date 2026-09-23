document.addEventListener('DOMContentLoaded', function() {
    // --- 1. LAZY LOADING LOGIC ---
    let lazyImages = document.querySelectorAll('.lazy-poster');
    let imageObserver = new IntersectionObserver(function(entries, observer) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                let img = entry.target;
                let folderUrl = img.getAttribute('data-folderurl');
                
                fetch('?action=get_image&url=' + encodeURIComponent(folderUrl))
                    .then(response => response.text())
                    .then(rawText => {
                        let cleanUrl = rawText.trim();
                        if (cleanUrl.startsWith('http')) img.src = cleanUrl;
                        else img.src = 'https://placehold.co/400x600/ffcccc/ff0000?text=Error';
                    })
                    .catch(() => img.src = 'https://placehold.co/400x600/ffcccc/ff0000?text=Network+Error');
                    
                observer.unobserve(img);
            }
        });
    }, { rootMargin: '0px 0px 300px 0px' });
    
    lazyImages.forEach(img => imageObserver.observe(img));

    // --- 2. OMDB TOOLTIP CACHING LOGIC ---
    const body = document.body;
    const useApi = body.getAttribute('data-movieapi') === 'true';
    const apiKey = body.getAttribute('data-omdbkey');

    if (useApi && apiKey) {
        const tooltip = document.createElement('div');
        tooltip.id = 'movie-tooltip';
        body.appendChild(tooltip);

        let hoverTimer;
        let currentFetchController;

        document.querySelectorAll('.movie-card').forEach(card => {
            card.addEventListener('mouseenter', (e) => {
                const title = card.getAttribute('data-title');
                const year = card.getAttribute('data-year');
                if (!title) return;

                hoverTimer = setTimeout(() => showTooltip(card, title, year), 400);
            });

            card.addEventListener('mouseleave', () => {
                clearTimeout(hoverTimer);
                if (currentFetchController) currentFetchController.abort();
                tooltip.style.display = 'none';
            });
        });

        function showTooltip(card, title, year) {
            const rect = card.getBoundingClientRect();
            let top = rect.top + window.scrollY;
            let left = rect.right + 20;

            if (left + 450 > window.innerWidth) left = rect.left - 470; 

            tooltip.style.top = `${top}px`;
            tooltip.style.left = `${left}px`;
            tooltip.innerHTML = `<div class="tooltip-msg">Loading details...</div>`;
            tooltip.style.display = 'flex';

            if (currentFetchController) currentFetchController.abort();
            currentFetchController = new AbortController();

            let localApiUrl = `?action=get_omdb&t=${encodeURIComponent(title)}&apikey=${encodeURIComponent(apiKey)}`;
            if (year) localApiUrl += `&y=${encodeURIComponent(year)}`;

            fetch(localApiUrl, { signal: currentFetchController.signal })
                .then(res => res.json())
                .then(data => {
                    if (data.Response === "True") {
                        const posterUrl = data.Poster !== 'N/A' ? data.Poster : 'https://placehold.co/400x600/e0e0e0/333333?text=No+Poster';
                        tooltip.innerHTML = `
                            <img src="${posterUrl}" class="tooltip-img" alt="Poster">
                            <div class="tooltip-info">
                                <h3>${data.Title} (${data.Year})</h3>
                                <p><strong>Genre:</strong> ${data.Genre}</p>
                                <p><strong>Rating:</strong> ⭐ ${data.imdbRating} / 10</p>
                                <p><strong>Runtime:</strong> ${data.Runtime}</p>
                                <p><strong>Director:</strong> ${data.Director}</p>
                                <p class="plot">${data.Plot}</p>
                            </div>
                        `;
                    } else {
                        tooltip.innerHTML = `<div class="tooltip-msg">Movie details not found.</div>`;
                    }
                })
                .catch(err => {
                    if (err.name !== 'AbortError') tooltip.innerHTML = `<div class="tooltip-msg">Error loading API.</div>`;
                });
        }
    }

    // --- 3. TOGGLE SAVE MOVIE LOGIC ---
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('save-btn')) {
            e.preventDefault(); 
            e.stopPropagation(); // Stops the click from opening the movie folder

            let btn = e.target;
            let card = btn.closest('.movie-card');
            let img = card.querySelector('.movie-poster');
            
            let movieData = {
                url: btn.getAttribute('data-url'),
                title: btn.getAttribute('data-title'),
                year: btn.getAttribute('data-year'),
                poster: img.src // Grabs the currently loaded poster image
            };

            fetch('?action=toggle_save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(movieData)
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'added') {
                    btn.classList.add('saved');
                } else if (res.status === 'removed') {
                    btn.classList.remove('saved');
                    // If we are currently inside the "Saved Movies" view, remove the card from the screen immediately
                    if (new URLSearchParams(window.location.search).get('col') === 'saved') {
                        card.remove();
                    }
                }
            });
        }
    });
});