/**
 * Hide ignored rows and add a "show ignored" button
 */
Array.prototype.forEach.call(
  document.querySelectorAll('.results tbody'),
  function(tbody) {
    var ignored = tbody.querySelectorAll('tr.ignore');
    console.log(ignored.length);
    if (ignored.length > 3) {
      tbody.classList.add('hideIgnore');
      var tr = '<tr class="ignore-header"><td colspan="3">'
        +'<button><span>Show</span><span>Hide</span> skipped elements (%n)</button>'
        +'</td></tr>';
      tr = tr.replace('%n', ignored.length + '');
      tbody.querySelector('.ignore').insertAdjacentHTML('beforebegin', tr);
      tbody.querySelector('.ignore-header button').addEventListener('click', function(){
        tbody.classList.toggle('hideIgnore');
      });
    }
  }
);
