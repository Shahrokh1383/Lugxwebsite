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
   * Search Form Handler
   */
  const searchButton = document.querySelector('.search-input button');
  if (searchButton) {
    searchButton.addEventListener('click', (e) => {
      e.preventDefault();
      const input = searchButton.parentElement.querySelector('input');
      if (input && input.value.trim() !== '') {
        console.log(`Searching for: ${input.value}`);
      }
    });
  }

  /**
   * Newsletter Subscription Handler
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

  /**
   * Email Validator Helper
   */
  function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(String(email).toLowerCase());
  }
});