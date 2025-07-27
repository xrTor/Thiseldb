function fetchFromIMDb() {
  const url = document.getElementById('imdbUrl').value;
  const match = url.match(/tt\d+/);
  if (!match) {
    alert("לא נמצא מזהה IMDb תקף");
    return;
  }
  const imdbId = match[0];
  const apiKey = '1ae9a12e'; // החלף כאן!

  fetch(`https://www.omdbapi.com/?i=${imdbId}&apikey=${1ae9a12e}`)
    .then(res => res.json())
    .then(data => {
      if (data.Response === "True") {
        document.getElementById('title_en').value = data.Title || '';
        document.getElementById('year').value = data.Year || '';
        document.getElementById('imdb_rating').value = data.imdbRating || '';
        document.getElementById('image_url').value = data.Poster || '';
        document.getElementById('plot').value = data.Plot || '';
      } else {
        alert("לא נמצאו נתונים עבור IMDb ID זה");
      }
    })
    .catch(err => {
      console.error(err);
      alert("שגיאה בשליפת נתונים");
    });
}
