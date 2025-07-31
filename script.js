var swiper = new Swiper(".swiper", {
  effect: "coverflow",
  grabCursor: true,
  centeredSlides: true,
  slidesPerView: "auto",
  spaceBetween: 50,
  loop: true,
  autoplay: {
    delay: 3000,
    disableOnInteraction: false
  },
  coverflowEffect: {
    rotate: 0,
    stretch: 20,
    depth: 100,
    modifier: 2,
    slideShadows: true
  },
  pagination: {
    el: ".swiper-pagination",
    clickable: true
  },
  navigation: {
    nextEl: ".swiper-button-next",
    prevEl: ".swiper-button-prev"
  },
  // Optional: Ensure centering works
  on: {
    init: function () {
      this.update();
    },
    imagesReady: function () {
      this.update();
    }
  }
});