using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.RazorPages;
using Microsoft.EntityFrameworkCore;
using Quote_inv.Areas.Identity.Data;

using Quote_inv.Models;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Threading.Tasks;

namespace Quote_inv.Pages.Dashboard
   
{
    public class IndexModel : PageModel
    {
        private readonly qandiContext _context;

        public IndexModel(qandiContext context)
        {
            _context = context;
        }

        public List<Client> Clients { get; set; }

        [BindProperty]
        public Quotation NewQuotation { get; set; } = new Quotation();

        [BindProperty]
        public List<ServiceItem> ServiceItems { get; set; } = new List<ServiceItem>();

        [BindProperty]
        public List<ProductItem> ProductItems { get; set; } = new List<ProductItem>();

        

        public async Task<IActionResult> OnPostAsync()
        {
            if (!ModelState.IsValid)
            {
                Clients = await _context.Clients.ToListAsync();
                return Page();
            }

            // Set the total amount
            if (NewQuotation.IsService)
            {
                NewQuotation.TotalAmount = ServiceItems.Sum(s => s.Price);
                NewQuotation.ServiceItems = ServiceItems;
            }
            else
            {
                NewQuotation.TotalAmount = ProductItems.Sum(p => p.Price * p.Quantity);
                NewQuotation.ProductItems = ProductItems;
            }

            _context.Quotations.Add(NewQuotation);
            await _context.SaveChangesAsync();

            return RedirectToPage("/Dashboard");
        }
    }
}