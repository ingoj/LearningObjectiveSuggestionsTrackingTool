(function() {
  var acc = document.getElementsByClassName('tracking-tool-accordion-button');
  var i;

  for (i = 0; i < acc.length; i++) {
    acc[i].addEventListener('click', function () {
      this.classList.toggle('tracking-tool-active');

      var panel = this.nextElementSibling;
      if (panel.style.display === 'block') {
        panel.style.display = 'none';
      } else {
        panel.style.display = 'block';
      }
    });
  }
})();