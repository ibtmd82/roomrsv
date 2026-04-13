// Starter JavaScript for disabling form submissions if there are invalid fields
(function () {
  'use strict'

  // Fetch all the forms we want to apply custom Bootstrap validation styles to
  //
  var forms = document.querySelectorAll('.needs-validation')
  //console.log(forms.entries());
  // Loop over them and prevent submission
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        //alert('hello');
        if (!form.checkValidity()) {
          //event.preventDefault()
          //event.stopPropagation()
        } else {
          //
          const data = new FormData(event.target);
          const value = Object.fromEntries(data.entries());
          //value.checkMotherNumber = data.getAll("checkMotherNumber");
          value.checkMotherNumber = $('#checkMotherNumber').prop('checked');
          value.checkDirectMul= $('#checkDirectMul').prop('checked');
          console.log({ value });
          //
          //save
          fetch('http://8.219.159.214:8080/Account/SaveCus', {
          //fetch('http://localhost:8511/Account/SaveCus', {
              method: 'POST',
              body: JSON.stringify({data:JSON.stringify(value)}),
              //body: {sdate:localStorage.getItem("ldate"), msgplays:localStorage.getItem("msgplays")},
              headers: {
                  'Content-type': 'application/json; charset=UTF-8',
                  'Accept':'application/json',
                  'clientid': localStorage.getItem("clientid")
                  //'Access-Control-Allow-Origin':'null',
                  //'Access-Control-Allow-Credentials':'true',
              }
          })
          .then(res => res.json())
          .then(data => { 
              console.log(JSON.stringify(data));
              //
              setTimeout(function(){ window.location.href = "usercuss.html"; }, 2000);
              //alert('success');
          })
          .catch(error => {
              //handle error
              alert(error);
              //console.log(error)
          });
        }
        form.classList.add('was-validated')
        event.preventDefault()
        event.stopPropagation()
        //return false
      }, false)
    })
})()