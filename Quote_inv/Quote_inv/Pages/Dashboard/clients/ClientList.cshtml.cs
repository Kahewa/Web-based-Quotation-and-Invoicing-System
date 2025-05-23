using Microsoft.AspNetCore.Mvc;
using Microsoft.AspNetCore.Mvc.RazorPages;
using System.Data;
using System.Data.SqlClient;


namespace Quote_inv.Pages.Dashboard.clients
{
    public class ClientListModel : PageModel
    {
        public List<ClientInfo> listClients = new List<ClientInfo>();

        public void OnGet()
        {
            try
            {
                String connectionString = "Data Source=LAPTOP-AEIFS8F3\\SQLEXPRESS;Initial Catalog=qandi;Integrated Security=True;Encrypt=False";

                using (SqlConnection connection = new SqlConnection(connectionString))
                {
                    connection.Open();
                    String sql = "SELECT * FROM clients";
                    using (SqlCommand command = new SqlCommand(sql, connection))
                    {
                        using (SqlDataReader reader = command.ExecuteReader())
                        {
                            while (reader.Read())
                            {
                                ClientInfo clientInfo = new ClientInfo();
                                clientInfo.id = "" + reader.GetInt32(0);
                                clientInfo.name = reader.GetString(1);
                                clientInfo.email = reader.GetString(2);
                                clientInfo.phone = reader.GetString(3);
                                clientInfo.created_at = reader.GetDateTime(4).ToString();
                                listClients.Add(clientInfo);
                            }
                        }

                    }
                }
            }
            
            catch (Exception ex)
            { 
                Console.WriteLine("Exception: " + ex.ToString());
            
            }
        }
    }


    public class  ClientInfo
    {
        public string id;
        public string name;
        public string email;
        public string phone;
        public string created_at;
    }


}
