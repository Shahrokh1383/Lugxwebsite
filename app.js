document.addEventListener('DOMContentLoaded', () => {
  /**
   * Sticky Header Functionality
   */
  const header = document.querySelector('.header-area');

  window.addEventListener('scroll', () => {
    if (window.scrollY > 80) {
      header.classList.add('is-sticky');
    } else {
      header.classList.remove('is-sticky');
    }
  });

  /**
   * Search Form Handler (global search in navbar)
   */
  const searchToggle = document.getElementById('search-toggle');
  const searchDropdown = document.getElementById('search-dropdown');

  if (searchToggle && searchDropdown) {
    searchToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      searchDropdown.classList.toggle('show');
    });

    // Hide when clicking outside
    document.addEventListener('click', (e) => {
      if (!searchDropdown.contains(e.target) && e.target !== searchToggle) {
        searchDropdown.classList.remove('show');
      }
    });

    // Simulate autocomplete suggestions (static)
    const suggestions = [
      'Assassin Creed',
      'Call of Duty MW II',
      'FIFA 23',
      'Need for Speed',
      'Cyberpunk 2077'
    ];
    const suggestionContainer = document.getElementById('suggestions-list');
    if (suggestionContainer) {
      suggestions.forEach(item => {
        const a = document.createElement('a');
        a.className = 'dropdown-item';
        a.innerHTML = `<i class="fa-solid fa-search"></i> ${item}`;
        a.addEventListener('click', () => {
          document.getElementById('search-input').value = item;
          searchDropdown.classList.remove('show');
        });
        suggestionContainer.appendChild(a);
      });
    }
  }

  /**
   * Mini Cart Toggle
   */
  const cartToggle = document.getElementById('cart-toggle');
  const cartDropdown = document.getElementById('cart-dropdown');

  if (cartToggle && cartDropdown) {
    cartToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      cartDropdown.classList.toggle('show');
    });
    document.addEventListener('click', (e) => {
      if (!cartDropdown.contains(e.target) && e.target !== cartToggle) {
        cartDropdown.classList.remove('show');
      }
    });
  }

  /**
   * User Dropdown (Bootstrap already handles toggle, but we'll use Bootstrap's dropdown)
   * No additional code needed if using Bootstrap's data-bs-toggle="dropdown".
   * We'll add an empty check only.
   */

  /**
   * Back to Top Button
   */
  const backToTopBtn = document.getElementById('back-to-top');
  if (backToTopBtn) {
    window.addEventListener('scroll', () => {
      if (window.scrollY > 300) {
        backToTopBtn.style.display = 'block';
      } else {
        backToTopBtn.style.display = 'none';
      }
    });
    backToTopBtn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  /**
   * Newsletter Subscription Handler (existing, kept)
   */
  const newsletterButton = document.querySelector('.newsletter-form button');
  if (newsletterButton) {
    newsletterButton.addEventListener('click', (e) => {
      e.preventDefault();
      const input = newsletterButton.parentElement.querySelector('input');
      if (input && validateEmail(input.value)) {
        alert('Thank you for subscribing!');
        input.value = '';
      } else {
        alert('Please enter a valid email address.');
      }
    });
  }

  function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
  }
});