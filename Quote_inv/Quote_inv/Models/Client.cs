using System.ComponentModel.DataAnnotations;

namespace Quote_inv.Models
{
    public class Client
    {
        public int id { get; set; }

       

        [Required]
        [Display(Name = "name")]
        public string name { get; set; }

        public string email { get; set; }
        public string phone { get; set; }
      
    }
}