<div id="enewsletter-modal" class="modal fade" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"> 
        <h5 class="modal-title mb-0">Subscribe to our newsletter to stay informed about all the ways to improve your sleep!</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="enewsletter-modal-form" class="enewsletter-modal-form">
          <div class="form-group mb-3">
            <label for="enewsletter-email-address" class="mb-2">Email Address</label>
            <input type="email" id="enewsletter-email-address" name="enewsletter-email-address" class="form-control" required />
          </div>
          <div class="form-check mb-3">
              <label class="form-check-label" for="enewsletter-optin">
                  <input class="form-check-input" type="checkbox" value="true" id="enewsletter-optin" name="enewsletter-optin" required />
                  <span>
                      I would like information, tips and offers about SleepScore solutions, products and services.
                  </span>
              </label>
          </div>
          <div class="form-group d-flex justify-content-center">
            <button type="submit" class="btn btn-primary">Submit</button>
          </div>
          <div class="my-4 text-center">
            <a href="/privacy-policy" target="_blank"><small>Privacy Statement</small></a>
          </div>
        </form>
        <div class="success-message d-none flex-column align-items-center">
            <p class="text-success my-4">Thanks for signing up for our newsletter!</p>
            <button type="button" class="btn btn-primary mb-3" data-dismiss="modal" aria-label="Close">
              Close
            </button>
        </div>
      </div>
    </div>
  </div>
</div>

<style>

    #enewsletter-modal,
    #enewsletter-modal .modal-title,
    #enewsletter-modal label {
        color: #000;
    }

    #enewsletter-modal a {
        color: #464c9c;
    }

    #enewsletter-modal input[type="email"] {
        background-color: #fff;
        color: #000;
    }

    #enewsletter-modal .btn-primary {
        background-color: #464c9c;
        border-color: #464c9c;
        border-radius: 40px;
        color: #fff;
        padding-left: 50px;
        padding-right: 50px;
    }

</style>

<script>

    setTimeout(() => { 

        const enewsLsKey = 'hvEnewsForm';
        const hasViewedEnewsletterForm = localStorage.getItem(enewsLsKey);
        const enewsletterForm = document.querySelector('#enewsletter-modal-form');

        function processEnewsletterForm(e) {
        
            e.preventDefault();
        
            const email = enewsletterForm.querySelector('input[type=email]').value;
            const enewsletterOptin = enewsletterForm.querySelector('input[type=checkbox]').value;
        
            if (!email || !enewsletterOptin) {
                return;
            }
        
            const auth_data = {
                email: email
            };
        
            const contact_data = {
                channels: {
                    email: {
                        subscribeStatus: 'subscribed',
                    }
                }
            };
        
            if (window.location.host === 'www.sleepscore.com') {
                contact_data['Website-SignUp-Welcome'] = true;
            } else {
                contact_data['testlist'] = true;
            }
        
            crdl('contact', auth_data, contact_data);
        console.log('enewsltter form', enewsletterForm)
            enewsletterForm.classList.add('d-none');
            const successMsg = document.querySelector('.success-message');
			console.log('successMsg', successMsg);
            successMsg.classList.remove('d-none');
            successMsg.classList.add('d-flex');
        
        }

        if (crdl && enewsletterForm) {
        
            const hasViewedEnewsletterForm = localStorage.getItem(enewsLsKey);
            if (!hasViewedEnewsletterForm) {

                crdl('connect', 'sleepscore-production', {
                    trackUrl: '//se.mail.sleepscore.com',
                    connectUrl: '//d.mail.sleepscore.com',
                    cookieDomain: 'sleepscore.com',
                    cookieLife: 365
                });
            
                enewsletterForm.addEventListener('submit', processEnewsletterForm);

                setTimeout(() => {
                    localStorage.setItem(enewsLsKey, true);
                    $('#enewsletter-modal').modal('show');
                }, 1000);

            } 
        
        }

    }, 3000);

</script>
