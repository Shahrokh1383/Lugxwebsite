document.addEventListener('DOMContentLoaded', function() {
    // Ensure global select and selectAll are available from main.js
    const select = window.select;
    const selectAll = window.selectAll;

    const carouselSlides = select('.carousel-slides');
    const slides = selectAll('.carousel-slide');
    const prevBtn = select('.prev-btn');
    const nextBtn = select('.next-btn');
    const dotsContainer = select('.carousel-dots');

    if (carouselSlides && slides.length > 0) {
        let currentIndex = 0;
        const totalSlides = slides.length;
        let slideInterval;
        const slideDuration = 4000; // Change slide every 4 seconds

        // Create dots dynamically
        if (dotsContainer) { // Ensure dotsContainer exists
            for (let i = 0; i < totalSlides; i++) {
                const dot = document.createElement('div');
                dot.classList.add('carousel-dot');
                if (i === 0) {
                    dot.classList.add('active');
                }
                dot.dataset.index = i;
                dotsContainer.appendChild(dot);
            }
        }
        const dots = selectAll('.carousel-dot'); // Select all dots after they are created

        const updateCarousel = () => {
            const offset = -currentIndex * 100; // Calculate offset for smooth transition
            carouselSlides.style.transform = `translateX(${offset}%)`;

            // Update active dot
            dots.forEach((dot, index) => {
                if (index === currentIndex) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
        };

        const goToNextSlide = () => {
            currentIndex = (currentIndex + 1) % totalSlides;
            updateCarousel();
        };

        const goToPrevSlide = () => {
            currentIndex = (currentIndex - 1 + totalSlides) % totalSlides;
            updateCarousel();
        };

        const startAutoSlide = () => {
            stopAutoSlide(); // Clear any existing interval first
            slideInterval = setInterval(goToNextSlide, slideDuration);
        };

        const stopAutoSlide = () => {
            clearInterval(slideInterval);
        };

        // Event Listeners for Buttons
        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                stopAutoSlide();
                goToPrevSlide();
                startAutoSlide(); // Restart auto-slide after manual interaction
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                stopAutoSlide();
                goToNextSlide();
                startAutoSlide(); // Restart auto-slide after manual interaction
            });
        }

        // Event Listeners for Dots
        if (dotsContainer) {
            dotsContainer.addEventListener('click', (e) => {
                if (e.target.classList.contains('carousel-dot')) {
                    const index = parseInt(e.target.dataset.index);
                    if (index !== currentIndex) {
                        stopAutoSlide();
                        currentIndex = index;
                        updateCarousel();
                        startAutoSlide(); // Restart auto-slide after manual interaction
                    }
                }
            });
        }

        // Pause auto-slide on hover
        const carouselContainer = select('.carousel-container');
        if (carouselContainer) {
            carouselContainer.addEventListener('mouseenter', stopAutoSlide);
            carouselContainer.addEventListener('mouseleave', startAutoSlide);
        }

        // Initialize carousel on load
        updateCarousel();
        startAutoSlide(); // Start auto-sliding when the page loads
    }
});
